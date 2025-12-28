<?php

namespace Mfonte\SparseDigest;

use Mfonte\SparseDigest\Strategy\SparseStrategy;
use InvalidArgumentException;

/**
 * SparseHasher - Sparse file hashing facade.
 *
 * Provides a convenient API for computing sparse (sampled) file hashes.
 * Uses the SparseStrategy by default, but accepts any HashingStrategy.
 *
 * @phpstan-consistent-constructor
 */
class SparseHasher
{
    /** @var HashingStrategy */
    protected $strategy;

    /** @var File */
    protected $file;

    /**
     * Factory method for SparseHasher.
     *
     * @param string|\SplFileInfo $file The file to hash.
     * @param HashingStrategy|null $strategy Optional custom hashing strategy.
     *
     * @return static
     *
     * @throws InvalidArgumentException if the file does not exist or the input is invalid.
     */
    public static function new($file, ?HashingStrategy $strategy = null): self
    {
        return new static($file, $strategy);
    }

    /**
     * Constructor.
     *
     * @param string|\SplFileInfo $file The file to hash.
     * @param HashingStrategy|null $strategy Optional custom hashing strategy.
     *
     * @throws InvalidArgumentException if the file does not exist or is of invalid type.
     */
    protected function __construct($file, ?HashingStrategy $strategy = null)
    {
        $this->strategy = $strategy ?: new SparseStrategy();
        $this->file = File::new($file);
    }

    /**
     * Computes the sparse hash for a given file.
     *
     * @param int $coverage Desired coverage percentage.
     * @param int $sampleSize Block size in bytes (must be divisible by 64).
     *
     * @return HashResult
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
     * @param int $sampleSize Block size in bytes (must be divisible by 64).
     *
     * @return HashResult
     */
    public function hashAs(string $algo, int $coverage = 10, int $sampleSize = 4096): HashResult
    {
        return $this->strategy->hashAs($this->file, $algo, $coverage, $sampleSize);
    }

    /**
     * Returns the compute time (in milliseconds) for the last hash computation.
     *
     * @return float
     */
    public function getComputeTime(): float
    {
        if ($this->strategy instanceof Strategy\AbstractStrategy) {
            return $this->strategy->getComputeTime();
        }
        return 0.0;
    }
}
