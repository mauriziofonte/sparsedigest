<?php

declare(strict_types=1);

namespace Mfonte\SparseDigest;

use Mfonte\SparseDigest\Exception\InvalidArgumentException;
use Mfonte\SparseDigest\Exception\RuntimeException;
use Mfonte\SparseDigest\Strategy\AbstractStrategy;
use Mfonte\SparseDigest\Strategy\FullStrategy;
use SplFileInfo;

/**
 * FileHasher - Full file hashing facade.
 *
 * Provides a consistent API for computing full-file hashes.
 * Use this when you need to hash the entire file content
 * (e.g., for cryptographic verification or when sparse hashing is not suitable).
 */
final class FileHasher
{
    /** @var HashingStrategy */
    private $strategy;

    /** @var File */
    private $file;

    /**
     * Factory method for FileHasher.
     *
     * @param string|SplFileInfo|resource $file The file to hash: a path, an SplFileInfo, or a readable and seekable stream.
     * @param HashingStrategy|null $strategy Optional custom hashing strategy.
     *
     * @return self
     *
     * @throws InvalidArgumentException if the file does not exist or the input is invalid.
     */
    public static function new($file, ?HashingStrategy $strategy = null): self
    {
        return new self($file, $strategy);
    }

    /**
     * Constructor.
     *
     * @param string|SplFileInfo|resource $file The file to hash: a path, an SplFileInfo, or a readable and seekable stream.
     * @param HashingStrategy|null $strategy Optional custom hashing strategy.
     *
     * @throws InvalidArgumentException if the file does not exist or is of invalid type.
     */
    private function __construct($file, ?HashingStrategy $strategy = null)
    {
        $this->strategy = $strategy ?? new FullStrategy();
        $this->file = File::new($file);
    }

    /**
     * Computes the full-file hash using the best available algorithm.
     *
     * @return HashResult
     *
     * @throws InvalidArgumentException if the parameters are invalid.
     * @throws RuntimeException if the file cannot be read.
     */
    public function hash(): HashResult
    {
        return $this->strategy->hash($this->file);
    }

    /**
     * Computes the full-file hash using the specified algorithm.
     *
     * @param string $algo The hashing algorithm to use (e.g., 'sha256', 'md5', 'xxh3').
     *
     * @return HashResult
     *
     * @throws InvalidArgumentException if the algorithm or the parameters are invalid.
     * @throws RuntimeException if the file cannot be read.
     */
    public function hashAs(string $algo): HashResult
    {
        return $this->strategy->hashAs($this->file, $algo);
    }

    /**
     * Tells whether another file has exactly the same content as this one.
     *
     * Sizes are compared first, so files of different size are rejected without being read.
     *
     * @param string|SplFileInfo|resource|File $other The file to compare with.
     *
     * @return bool
     *
     * @throws InvalidArgumentException if the other file is invalid.
     * @throws RuntimeException if a file cannot be read.
     */
    public function matches($other): bool
    {
        $otherFile = $other instanceof File ? $other : File::new($other);
        if ($otherFile->getSize() !== $this->file->getSize()) {
            return false;
        }

        return $this->strategy->hash($this->file)->equals($this->strategy->hash($otherFile));
    }

    /**
     * Returns the compute time (in milliseconds) for the last hash computation.
     *
     * Returns 0.0 when no hash has been computed yet, or when a custom strategy
     * that does not extend AbstractStrategy is used.
     *
     * @return float
     */
    public function getComputeTime(): float
    {
        if ($this->strategy instanceof AbstractStrategy) {
            return $this->strategy->getComputeTime();
        }

        return 0.0;
    }
}
