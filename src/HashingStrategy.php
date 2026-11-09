<?php

declare(strict_types=1);

namespace Mfonte\SparseDigest;

/**
 * HashingStrategy Interface.
 *
 * Defines the contract for all hashing strategies (sparse, full, or custom).
 * Any implementation of a hashing algorithm must implement this interface.
 *
 * This follows the Strategy pattern, allowing different hashing behaviors
 * to be swapped at runtime.
 */
interface HashingStrategy
{
    /**
     * Computes a hash for the given file using default parameters.
     *
     * @param File $file The file to be hashed.
     * @param int $coverage Desired coverage percentage of the file (used by sparse strategies).
     * @param int $sampleSize Block size in bytes for each sample (used by sparse strategies).
     * @return HashResult
     */
    public function hash(File $file, int $coverage = 10, int $sampleSize = 4096): HashResult;

    /**
     * Computes a hash for the given file using a specific algorithm.
     *
     * @param File $file The file to be hashed.
     * @param string $algo The algorithm to use for the hash representation.
     * @param int $coverage Desired coverage percentage of the file (used by sparse strategies).
     * @param int $sampleSize Block size in bytes for each sample (used by sparse strategies).
     * @return HashResult
     */
    public function hashAs(File $file, string $algo, int $coverage = 10, int $sampleSize = 4096): HashResult;
}
