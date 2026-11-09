<?php

declare(strict_types=1);

namespace Mfonte\SparseDigest;

use Mfonte\SparseDigest\Exception\InvalidArgumentException;
use Mfonte\SparseDigest\Exception\RuntimeException;
use Mfonte\SparseDigest\Strategy\AbstractStrategy;
use Mfonte\SparseDigest\Strategy\SparseStrategy;
use SplFileInfo;

/**
 * SparseHasher - Sparse file hashing facade.
 *
 * Provides a convenient API for computing sparse (sampled) file hashes.
 * Uses the SparseStrategy by default, but accepts any HashingStrategy.
 */
final class SparseHasher
{
    /** @var HashingStrategy */
    private $strategy;

    /** @var File */
    private $file;

    /**
     * Factory method for SparseHasher.
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
        $this->strategy = $strategy ?? new SparseStrategy();
        $this->file = File::new($file);
    }

    /**
     * Computes the sparse hash for a given file.
     *
     * @param int $coverage Desired coverage percentage.
     * @param int $sampleSize Block size in bytes (positive multiple of 64).
     *
     * @return HashResult
     *
     * @throws InvalidArgumentException if the parameters are invalid.
     * @throws RuntimeException if the file cannot be read.
     */
    public function hash(int $coverage = 10, int $sampleSize = 4096): HashResult
    {
        return $this->strategy->hash($this->file, $coverage, $sampleSize);
    }

    /**
     * Computes the sparse hash for a given file using a custom hashing algorithm.
     *
     * @param string $algo The hashing algorithm to use for the output representation.
     * @param int $coverage Desired coverage percentage.
     * @param int $sampleSize Block size in bytes (positive multiple of 64).
     *
     * @return HashResult
     *
     * @throws InvalidArgumentException if the algorithm or the parameters are invalid.
     * @throws RuntimeException if the file cannot be read.
     */
    public function hashAs(string $algo, int $coverage = 10, int $sampleSize = 4096): HashResult
    {
        return $this->strategy->hashAs($this->file, $algo, $coverage, $sampleSize);
    }

    /**
     * Tells whether another file has the same sparse hash as this one, with the same sampling parameters.
     *
     * Sizes are compared first, so files of different size are rejected without being read.
     * As with hash(), changes outside the sampled blocks of a binary file are not detected.
     *
     * @param string|SplFileInfo|resource|File $other The file to compare with.
     * @param int $coverage Desired coverage percentage.
     * @param int $sampleSize Block size in bytes (positive multiple of 64).
     *
     * @return bool
     *
     * @throws InvalidArgumentException if the other file or the parameters are invalid.
     * @throws RuntimeException if a file cannot be read.
     */
    public function matches($other, int $coverage = 10, int $sampleSize = 4096): bool
    {
        $otherFile = $other instanceof File ? $other : File::new($other);
        if ($otherFile->getSize() !== $this->file->getSize()) {
            return false;
        }

        return $this->strategy->hash($this->file, $coverage, $sampleSize)
            ->equals($this->strategy->hash($otherFile, $coverage, $sampleSize));
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
