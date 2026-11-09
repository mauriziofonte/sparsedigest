<?php

declare(strict_types=1);

namespace Mfonte\SparseDigest\Strategy;

use Mfonte\SparseDigest\Exception\InvalidArgumentException;
use Mfonte\SparseDigest\Exception\RuntimeException;
use Mfonte\SparseDigest\File;
use Mfonte\SparseDigest\HashingStrategy;
use Mfonte\SparseDigest\HashResult;

/**
 * FullStrategy - Full-file hashing strategy.
 *
 * Implements full-file digesting (hashing the entire file content).
 * This is provided as a convenient wrapper around PHP's native hash_file()
 * with consistent HashResult object output.
 */
final class FullStrategy extends AbstractStrategy implements HashingStrategy
{
    /**
     * {@inheritdoc}
     *
     * For full hashing, coverage and sampleSize are ignored since we hash the entire file.
     *
     * @throws RuntimeException if the file cannot be read.
     */
    public function hash(File $file, int $coverage = 100, int $sampleSize = 4096): HashResult
    {
        return $this->hashAs($file, $this->defaultAlgo, $coverage, $sampleSize);
    }

    /**
     * {@inheritdoc}
     *
     * For full hashing, coverage and sampleSize are ignored since we hash the entire file.
     *
     * @throws InvalidArgumentException if the algorithm is not supported.
     * @throws RuntimeException if the file cannot be read.
     */
    public function hashAs(File $file, string $algo, int $coverage = 100, int $sampleSize = 4096): HashResult
    {
        $this->preventInvalidAlgorithm($algo);

        return $this->stopwatch(static function () use ($file, $algo): HashResult {
            return $file->hashWith($algo);
        });
    }
}
