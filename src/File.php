<?php

declare(strict_types=1);

namespace Mfonte\SparseDigest;

use Mfonte\SparseDigest\Exception\BadMethodCallException;
use Mfonte\SparseDigest\Exception\InvalidFileException;
use Mfonte\SparseDigest\Exception\InvalidRangeException;
use Mfonte\SparseDigest\Exception\IOException;
use Mfonte\SparseDigest\Exception\UnsupportedAlgorithmException;
use SplFileInfo;

/**
 * File.
 *
 * Represents a file on the filesystem and provides methods for reading its contents.
 * This class encapsulates file I/O and reading data segments used by hashing strategies.
 */
final class File
{
    /** @var string */
    private $path;

    /** @var int */
    private $size;

    /** @var string|null */
    private $mimeType = null;

    /** @var array<string, string> */
    private $chunks = [];

    /** @var resource|null */
    private $handle = null;

    /** @var bool Whether the handle was passed in by the caller (and must not be closed by this object). */
    private $isStream = false;

    /**
     * Factory method.
     *
     * @param string|SplFileInfo|resource $file A path, an SplFileInfo, or a readable and seekable stream.
     * @return self
     * @throws InvalidFileException if the file is invalid.
     */
    public static function new($file): self
    {
        return new self($file);
    }

    /**
     * Constructor.
     *
     * @param string|SplFileInfo|resource $file
     * @throws InvalidFileException
     */
    private function __construct($file)
    {
        if (is_resource($file)) {
            $this->initFromStream($file);

            return;
        }

        if ($file instanceof SplFileInfo) {
            $realPath = $file->getRealPath();
            $path = $realPath !== false ? $realPath : $file->getPathname();
        } elseif (is_string($file)) {
            $path = $file;
        } else {
            $type = is_object($file) ? get_class($file) : gettype($file);

            throw new InvalidFileException("Invalid file type: {$type}");
        }

        if ($path === '' || ! file_exists($path)) {
            throw new InvalidFileException("File '{$path}' does not exist.");
        }
        if (! is_file($path)) {
            throw new InvalidFileException("Path '{$path}' is not a file.");
        }
        if (! is_readable($path)) {
            throw new InvalidFileException("File '{$path}' is not readable.");
        }

        $size = @filesize($path);
        if ($size === false) {
            throw new InvalidFileException("Unable to determine the size of file '{$path}'.");
        }

        $this->path = $path;
        $this->size = $size;
    }

    /**
     * Initializes the instance from an already open stream.
     *
     * The stream must be readable and seekable, and its size must be known (fstat).
     * It is not closed by this object: the caller keeps ownership of it.
     *
     * @param resource $stream
     * @throws InvalidFileException
     */
    private function initFromStream($stream): void
    {
        if (get_resource_type($stream) !== 'stream') {
            throw new InvalidFileException('Invalid file type: resource of type ' . get_resource_type($stream));
        }

        $meta = stream_get_meta_data($stream);
        if (! $meta['seekable']) {
            throw new InvalidFileException('The stream is not seekable.');
        }
        if (strpbrk($meta['mode'], 'r+') === false) {
            throw new InvalidFileException("The stream is not readable (mode '{$meta['mode']}').");
        }

        $stat = @fstat($stream);
        if ($stat === false || ! isset($stat['size'])) {
            throw new InvalidFileException('Unable to determine the size of the stream.');
        }

        // 'uri' is missing for some stream types (e.g. sockets) despite what the stubs say.
        $uri = array_key_exists('uri', $meta) ? (string) $meta['uri'] : '';
        $this->path = $uri !== '' ? $uri : 'php://stream';
        $this->size = (int) $stat['size'];
        $this->handle = $stream;
        $this->isStream = true;
    }

    /**
     * Whether this instance wraps a caller-provided stream rather than a path.
     *
     * @return bool
     */
    public function isStream(): bool
    {
        return $this->isStream;
    }

    /**
     * Returns the file path.
     *
     * @return string
     */
    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * Returns the file size.
     *
     * @return int
     */
    public function getSize(): int
    {
        return $this->size;
    }

    /**
     * Returns the MIME type.
     *
     * @return string
     */
    public function getMimeType(): string
    {
        if ($this->mimeType === null) {
            // mime_content_type() is provided by ext-fileinfo, which is optional.
            $mime = false;
            if (function_exists('mime_content_type')) {
                $mime = $this->isStream ? @mime_content_type($this->getHandle()) : @mime_content_type($this->path);
            }
            $this->mimeType = $mime !== false ? $mime : 'application/octet-stream';
        }

        return $this->mimeType;
    }

    /**
     * Computes a full-file hash using the specified algorithm.
     *
     * @param string $algorithm Any algorithm listed by hash_algos().
     * @return HashResult
     *
     * @throws UnsupportedAlgorithmException if the algorithm is not supported.
     * @throws IOException if the file cannot be read.
     */
    public function hashWith(string $algorithm): HashResult
    {
        if (! in_array($algorithm, hash_algos(), true)) {
            throw UnsupportedAlgorithmException::forAlgorithm($algorithm);
        }
        $hash = $this->isStream ? $this->hashStream($algorithm) : @hash_file($algorithm, $this->path, true);
        if ($hash === false) {
            $error = error_get_last();

            throw new IOException("Unable to hash file '{$this->path}': " . ($error['message'] ?? 'unknown error'));
        }

        return HashResult::new($hash, $algorithm, false);
    }

    /**
     * Hashes the whole wrapped stream, from offset 0.
     *
     * @param string $algorithm
     * @return string|false
     */
    private function hashStream(string $algorithm)
    {
        $handle = $this->getHandle();
        if (fseek($handle, 0) === -1) {
            return false;
        }

        $ctx = hash_init($algorithm);
        hash_update_stream($ctx, $handle);

        return hash_final($ctx, true);
    }

    /**
     * Determines if the file cannot be sampled, because it is too small.
     *
     * The algorithm calculates totalBlocks = (fileSize × coverage / 100) / sampleSize.
     * If totalBlocks < 4, the file is too small for sparse hashing.
     *
     * @param int $coverage    Coverage percentage (1-100).
     * @param int $sampleSize  Block size (in bytes) for each sample.
     *
     * @return bool True if the file cannot be sampled (too small), false otherwise.
     */
    public function cantBeSampled(int $coverage = 10, int $sampleSize = 4096): bool
    {
        $fileSize = $this->getSize();
        $totalSampleBytes = (int) floor($fileSize * $coverage / 100);
        $totalBlocks = (int) floor($totalSampleBytes / $sampleSize);

        return $totalBlocks < 4 || $fileSize < ($totalBlocks * $sampleSize);
    }

    /**
     * Fast binary detection using null-byte and control character checks.
     *
     * This method examines the first 512 bytes of the file to determine if it's binary:
     * - If a null byte is found, the file is considered binary (covers images, videos, archives, executables)
     * - If control characters (< 9 or 14-31) are found, the file is considered binary
     *
     * This heuristic is fast and covers the vast majority of binary file formats.
     * Text files (including UTF-8 with multibyte characters) will be detected as text.
     *
     * @return bool True if likely binary, false if likely textual.
     */
    public function isLikelyBinary(): bool
    {
        // Read first 512 bytes - enough for most binary format headers
        $handle = $this->getHandle();
        if (fseek($handle, 0) === -1) {
            return true; // Assume binary on error
        }

        $header = fread($handle, 512);
        if ($header === false || $header === '') {
            return true;
        }

        // Fast null-byte check - most binary files have nulls in header
        if (strpos($header, "\0") !== false) {
            return true;
        }

        // Check for control characters that indicate binary content
        for ($i = 0, $len = strlen($header); $i < $len; $i++) {
            $byte = ord($header[$i]);
            // Control chars (except \t=9, \n=10, \v=11, \f=12, \r=13) suggest binary
            if ($byte < 9 || ($byte > 13 && $byte < 32)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Reads exactly $length bytes from the file at the given offset.
     *
     * This is a fast, simplified version that reads raw bytes without null-byte processing.
     * The null-byte replacement logic was removed as it added significant overhead without
     * meaningful benefit for hash uniqueness (the hash function handles this).
     *
     * @param int $offset The starting offset in the file from which to read data.
     * @param int $length The number of bytes to read.
     *
     * @return string A string containing exactly $length bytes.
     *
     * @throws InvalidRangeException if $offset is negative, $length is less than 1, or the requested range exceeds the file size.
     * @throws IOException if seeking or reading from the file fails.
     */
    public function readBytesAtOffset(int $offset, int $length): string
    {
        if ($offset < 0 || $length < 1) {
            throw new InvalidRangeException("Invalid offset or length: offset={$offset}, length={$length}");
        }

        if ($offset + $length > $this->size) {
            throw new InvalidRangeException("Read range exceeds file size: offset={$offset}, length={$length}, size={$this->size}");
        }

        $handle = $this->getHandle();
        if (fseek($handle, $offset) === -1) {
            throw new IOException("Unable to seek to offset {$offset} in file '{$this->path}'.");
        }

        $data = fread($handle, $length);
        if ($data === false || strlen($data) !== $length) {
            throw new IOException("Unable to read {$length} bytes at offset {$offset} in file '{$this->path}'.");
        }

        return $data;
    }

    /**
     * Reads *at least* $length bytes of "meaningful" data (i.e. non-null bytes) from the file at the given offset.
     *
     * This method attempts to return a block of data that is at least $length bytes long, but with all "\0" (null)
     * bytes converted to their respective segment length.
     * If the initial read does not yield enough non-null bytes, it enters a loop where it
     * samples subsequent blocks (by advancing $length bytes per iteration) until the accumulated data meets
     * the required size. If, after up to 1024 iterations, the accumulated data is still short, the remaining
     * bytes are padded with "\0" characters.
     *
     * Additionally, if the requested block (offset + length) would exceed the file size, an exception is thrown.
     * However, the internal helper method (readNonNullData) is designed to adjust the reading position when the
     * requested block would run off the end of the file by moving backwards, in order to avoid repeatedly sampling
     * the same block.
     *
     * @param int $offset The starting offset in the file from which to read data.
     * @param int $length The number of bytes to return after null bytes have been removed.
     *
     * @return string A string containing exactly $length bytes of meaningful (non-null) data.
     *
     * @throws InvalidRangeException if $offset is negative, $length is less than 1, or the requested range exceeds the file size.
     * @throws IOException if seeking or reading from the file fails.
     */
    public function readMeaningfulBytesAtOffset(int $offset, int $length): string
    {
        // return from cache if we already have this offset:length combination in memory
        $cacheKey = "{$offset}:{$length}";
        if (isset($this->chunks[$cacheKey])) {
            return $this->chunks[$cacheKey];
        }

        if ($offset < 0 || $length < 1) {
            throw new InvalidRangeException("Invalid offset or length: offset={$offset}, length={$length}");
        }

        if ($offset + $length > $this->size) {
            throw new InvalidRangeException("Read range exceeds file size: offset={$offset}, length={$length}, size={$this->size}");
        }

        $data = $this->readNonNullData($offset, $length);

        if (strlen($data) < $length) {
            // try to fill the gaps by reading forward for a maximum of $this->size / $length iterations
            $iterations = (int) floor($this->size / $length);
            for ($i = 1; $i <= $iterations; $i++) {
                // Calculate next offset, ensuring we don't exceed file bounds
                $nextOffset = $offset + ($i * $length);
                if ($nextOffset + $length > $this->size) {
                    break; // Can't read beyond file size
                }
                $data .= $this->readNonNullData($nextOffset, $length);
                if (strlen($data) >= $length) {
                    break;
                }
            }

            // right fill with null bytes, if necessary
            if (strlen($data) < $length) {
                $data .= str_repeat("\0", $length - strlen($data));
            }
        }

        // Truncate to exactly $length bytes to ensure consistent output
        $data = substr($data, 0, $length);

        $this->chunks[$cacheKey] = $data;

        return $data;
    }

    /**
     * Reads a block of $length bytes from the file at the specified offset, removes all "\0" (null) bytes,
     * and, if necessary, adjusts the reading direction to avoid sampling the same block repeatedly.
     * IMPORTANT: it is *not* guaranteed that this method will return a stream of exactly $length bytes.
     * This is because, we treat null bytes as "no data", so, we strip them out and replace them with their segment length.
     *
     * @param int $offset The file offset from which to start reading.
     * @param int $length The exact number of bytes to read from the file (but, not tied to the final output).
     *
     * @return string A string containing $length bytes read from the file, with all null bytes removed and replaced by their
     *                segment length. If the block was read in backward mode, the string is reversed to restore logical order.
     *
     * @throws IOException if seeking fails or if the file cannot yield exactly $length bytes.
     */
    private function readNonNullData(int $offset, int $length): string
    {
        if ($length < 1) {
            throw new InvalidRangeException("Invalid length: {$length}");
        }

        // Detect if we need to read backward
        $isBackward = false;
        if ($offset + $length > $this->size) {
            $isBackward = true;
            $offset = $this->size - abs($this->size - $offset) - $length;
        }

        // Ensure offset is valid
        if ($offset < 0) {
            throw new InvalidRangeException("Invalid offset: {$offset}");
        }

        $handle = $this->getHandle();
        if (fseek($handle, $offset) === -1) {
            throw new IOException("Unable to seek to offset {$offset} in file '{$this->path}'.");
        }

        // Read exactly $length bytes
        $data = fread($handle, $length);
        if ($data === false || strlen($data) !== $length) {
            throw new IOException("Unable to read {$length} bytes at offset {$offset} in file '{$this->path}'.");
        }

        // replace each segment of null bytes with its respective length
        // this will increase data stream uniqueness
        $data = preg_replace_callback('/\x00+/', static function (array $match): string {
            return (string) strlen($match[0]);
        }, $data);
        if ($data === null) {
            throw new IOException('Unable to process null bytes: ' . preg_last_error());
        }

        // Reverse data if reading backward
        return $isBackward ? strrev($data) : $data;
    }

    /**
     * Returns a file handle.
     *
     * @return resource
     * @throws IOException
     */
    private function getHandle()
    {
        if ($this->handle === null) {
            $this->open();
        }
        if (! is_resource($this->handle)) {
            throw new IOException("Unable to open file '{$this->path}' for reading.");
        }

        return $this->handle;
    }

    /**
     * Opens the file for reading.
     *
     * @return void
     * @throws IOException
     */
    private function open(): void
    {
        $handle = @fopen($this->path, 'rb');
        if ($handle === false) {
            throw new IOException("Unable to open file '{$this->path}' for reading.");
        }
        $this->handle = $handle;
    }

    /**
     * Closes the file handle.
     */
    private function close(): void
    {
        if ($this->isStream) {
            // The caller owns the stream: never close it.
            $this->handle = null;

            return;
        }
        if (is_resource($this->handle)) {
            fclose($this->handle);
            $this->handle = null;
        }
    }

    /**
     * To Array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'path' => $this->path,
            'size' => $this->size,
        ];
    }

    /**
     * Destructor.
     */
    public function __destruct()
    {
        $this->close();
    }

    /**
     * String representation.
     *
     * @return string
     */
    public function __toString()
    {
        return $this->path;
    }

    /**
     * Cloning is not allowed.
     */
    public function __clone()
    {
        throw new BadMethodCallException('Cloning is not allowed for ' . __CLASS__);
    }

    /**
     * Serialization is not allowed.
     */
    public function __sleep()
    {
        throw new BadMethodCallException('Serialization is not allowed for ' . __CLASS__);
    }

    /**
     * Unserialization is not allowed.
     */
    public function __wakeup()
    {
        throw new BadMethodCallException('Unserialization is not allowed for ' . __CLASS__);
    }
}
