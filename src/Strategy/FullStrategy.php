<?php

namespace Mfonte\SparseDigest\Strategy;

use Mfonte\SparseDigest\File;
use Mfonte\SparseDigest\HashResult;
use Mfonte\SparseDigest\HashingStrategy;
use InvalidArgumentException;

/**
 * FullStrategy - Full-file hashing strategy.
 *
 * Implements full-file digesting (hashing the entire file content).
 * This is provided as a convenient wrapper around PHP's native hash_file()
 * with consistent HashResult object output.
 */
class FullStrategy extends AbstractStrategy implements HashingStrategy
{
    /**
     * {@inheritdoc}
     *
     * For full hashing, coverage and sampleSize are ignored since we hash the entire file.
     */
    public function hash(File $file, int $coverage = 100, int $sampleSize = 4096): HashResult
    {
        return $this->hashAs($file, $this->defaultAlgo, $coverage, $sampleSize);
    }

    /**
     * {@inheritdoc}
     *
     * For full hashing, coverage and sampleSize are ignored since we hash the entire file.
     */
    public function hashAs(File $file, string $algo, int $coverage = 100, int $sampleSize = 4096): HashResult
    {
        if (!in_array($algo, hash_algos(), true)) {
            throw new InvalidArgumentException("Invalid hash algorithm: {$algo}");
        }

        $result = $this->stopwatch(function () use ($file, $algo) {
            return hash_file($algo, $file->getPath(), true);
        });

        return HashResult::new($result);
    }
}
