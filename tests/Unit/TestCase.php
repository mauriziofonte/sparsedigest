<?php

namespace Mfonte\SparseDigest\Test\Unit;

use PHPUnit\Framework\TestCase as PHPUnitTestCase;

abstract class TestCase extends PHPUnitTestCase
{
    /** @var string */
    protected $fixturesDir;

    /** @var string */
    protected $testDir;

    /**
     * @var string[]
     */
    protected $tempFiles = [];

    protected function setUp(): void
    {
        // Set test directory as the current directory of this file.
        $this->testDir = rtrim(__DIR__, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

        // fixtures directory is on the parent folder, on "Fixtures" folder.
        $this->fixturesDir = realpath($this->testDir . '..' . DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'Fixtures' . DIRECTORY_SEPARATOR;
    }

    protected function tearDown(): void
    {
        // Remove any temporary files created during the test.
        foreach ($this->tempFiles as $file) {
            if (file_exists($file)) {
                @unlink($file);
            }
        }
        $this->tempFiles = [];
    }

    /**
     * Returns the full path to a fixture file.
     *
     * @param string $fileName
     * @return string
     */
    protected function getFixtureFile(string $fileName): string
    {
        return $this->fixturesDir . $fileName;
    }

    /**
     * Returns a temporary file containing the specified contents.
     *
     * @param string $contents
     *
     * @return string
     */
    protected function createTempFileWithContents(string $contents): string
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'sparsedigest_');

        $handle = fopen($tempFile, 'wb');
        if ($handle === false) {
            throw new \RuntimeException("Unable to open temporary file '$tempFile' for writing.");
        }

        try {
            $bytesWritten = fwrite($handle, $contents);
            if ($bytesWritten === false) {
                throw new \RuntimeException("Error writing to temporary file '$tempFile'.");
            }

            fclose($handle);

            $this->tempFiles[] = $tempFile;

            return $tempFile;
        } catch (\Throwable $e) {
            fclose($handle);
            if (file_exists($tempFile)) {
                @unlink($tempFile);
            }

            throw $e;
        }
    }

    /**
     * Returns a temporary file of the specified size, filled with random data of either binary or UTF-8 text form.
     *
     * @param int $size
     * @param bool $binary - If true, the file will be filled with binary data. Otherwise, it will be filled with UTF-8 text data.
     *
     * @return string
     */
    protected function createTempFileOfSize(int $size, bool $binary = true): string
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'sparsedigest_');

        $handle = fopen($tempFile, 'wb');
        if ($handle === false) {
            throw new \RuntimeException("Unable to open temporary file '$tempFile' for writing.");
        }

        try {
            $bytesWritten = 0;
            while ($bytesWritten < $size) {
                $bytesToWrite = min($size - $bytesWritten, 4096);

                // generate either binary or text data
                $data = ($binary) ? random_bytes($bytesToWrite) : $this->generateUtf8PrintableString($bytesToWrite);
                $dataWritten = fwrite($handle, $data);

                if ($dataWritten === false) {
                    throw new \RuntimeException("Error writing to temporary file '$tempFile'.");
                }

                $bytesWritten += $dataWritten;
            }

            fclose($handle);

            $this->tempFiles[] = $tempFile;

            return $tempFile;
        } catch (\Throwable $e) {
            fclose($handle);
            if (file_exists($tempFile)) {
                @unlink($tempFile);
            }

            throw $e;
        }
    }

    /**
     * Returns a temporary file of the specified size, filled with random data of either binary or UTF-8 text form, using $offsets as indicator of
     * where to insert NULL bytes.
     *
     * @param int $size - The size of the file to create.
     * @param array<int, int> $offsets - Array of key=>value pairs, where key is the offset, and value is the size of the NULL bytes to insert.
     * @param bool $binary - If true, the file will be filled with binary data. Otherwise, it will be filled with UTF-8 text data.
     *
     * @return string
     */
    protected function createTempFileOfSizeWithNullBytes(int $size, array $offsets, bool $binary = true) : string
    {
        try {
            $tempFile = $this->createTempFileOfSize($size, $binary);

            $handle = fopen($tempFile, 'r+b');
            if ($handle === false) {
                throw new \RuntimeException("Unable to open temporary file '$tempFile' for writing.");
            }

            foreach ($offsets as $offset => $nullSize) {
                $seek = @fseek($handle, $offset);
                if ($seek === -1) {
                    throw new \RuntimeException("Error seeking to offset $offset in temporary file '$tempFile'.");
                }

                $dataWritten = fwrite($handle, str_repeat("\0", $nullSize));
                if ($dataWritten === false) {
                    throw new \RuntimeException("Error writing to temporary file '$tempFile'.");
                }
            }

            fclose($handle);

            return $tempFile;
        } catch (\Throwable $e) {
            throw $e;
        }
    }

    /**
     * Generates random offsets and lengths for NULL blocks within a given size.
     *
     * This method determines random offsets and random NULL lengths, ensuring that the total number of NULL blocks
     * does not exceed the size divided by the sample size. The offsets and lengths are returned as an associative array.
     *
     * @param int $size The total size within which to generate offsets.
     * @param int $sampleSize The block size for each NULL block.
     * @param bool $isBinary Optional. Indicates if the data is binary. Default is true.
     *
     * @return array<int, int> An associative array where keys are offsets and values are lengths of NULL blocks.
     * @throws \Exception If it was not possible to gather sufficient entropy.
     */
    protected function getRandomOffsets(int $size, int $sampleSize, bool $isBinary = true): array
    {
        // determine random offsets and random NULL lengths, length-aware. Block size is $sampleSize
        $nullOffsets = [];
        $nullLengths = [];
        $nulls = 0;
        if ($isBinary) {
            $maxNulls = (int) round($size / $sampleSize, 0); // we allow a maximum of NULL blocks that represents the whole file
            $minNullOffset = 0;
            $maxNullOffset = $size - $sampleSize;
        } else {
            // in binary mode, in order to have a truthy return from canBeSampled() method on File class, we *shall not*
            // write NULLs on the first 2 blocks and last 2 blocks of the file
            $maxNulls = (int) round($size / $sampleSize, 0) - 4; // assume we're skipping the first 2 blocks and last 2 blocks
            $minNullOffset = $sampleSize * 2;
            $maxNullOffset = $size - $sampleSize * 3;
        }
        
        $nulls = random_int(1, $maxNulls);
        for ($i = 0; $i < $nulls; $i++) {
            $nullOffsets[] = random_int($minNullOffset, $maxNullOffset);
            $nullLengths[] = random_int(1, $sampleSize);
        }

        // merge the offsets and lengths
        return array_combine($nullOffsets, $nullLengths);
    }

    /**
     * Generates a random UTF-8 printable string of a given length.
     * This includes Latin, Cyrillic, Chinese, and Emoji characters.
     *
     * @return string
     */
    private function generateUtf8PrintableString(int $length): string
    {
        $charPools = [
            [0x0020, 0x007E],   // Basic Latin (printable ASCII)
            [0x00A1, 0x02AF],   // Latin Extended + IPA Extensions
            [0x0400, 0x04FF],   // Cyrillic
            [0x4E00, 0x9FFF],   // CJK Unified Ideographs (Chinese)
            [0x1F300, 0x1F64F], // Emoji range (faces, emotions)
            [0x1F680, 0x1F6FF], // Transport and map symbols
            [0x1F900, 0x1F9FF], // Supplemental Symbols and Pictographs
        ];

        $utf8String = '';

        while (mb_strlen($utf8String, 'UTF-8') < $length) {
            // Select a random Unicode range
            $range = $charPools[array_rand($charPools)];
            $codePoint = random_int($range[0], $range[1]);

            // Convert to UTF-8 character
            $char = mb_chr($codePoint, 'UTF-8');
            if ($char !== false) {
                $utf8String .= $char;
            }
        }

        return mb_substr($utf8String, 0, $length, 'UTF-8');
    }
}
