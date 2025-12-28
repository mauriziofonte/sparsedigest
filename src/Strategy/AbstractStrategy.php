<?php

namespace Mfonte\SparseDigest\Strategy;

use InvalidArgumentException;

/**
 * AbstractStrategy - Base class for hashing strategies.
 *
 * This abstract class provides common functionality for all hashing strategies.
 * It cannot be instantiated directly - concrete implementations must extend it.
 */
abstract class AbstractStrategy
{
    /** @var string */
    protected $defaultAlgo;

    /** @var float */
    protected $computeTime = 0.0;

    /**
     * Constructor.
     *
     * Determines the default algorithm based on a preferred list.
     */
    public function __construct()
    {
        $this->defaultAlgo = $this->determineBestAlgo();
    }

    /**
     * Returns the compute time (in milliseconds) for the last digest computation.
     *
     * @return float
     */
    public function getComputeTime(): float
    {
        return $this->computeTime;
    }

    /**
     * Returns the default algorithm being used.
     *
     * @return string
     */
    public function getDefaultAlgorithm(): string
    {
        return $this->defaultAlgo;
    }

    /**
     * Wraps a callback to measure its execution time.
     *
     * @param callable $callback
     * @return mixed
     */
    protected function stopwatch(callable $callback)
    {
        $start = microtime(true);
        $result = $callback();
        $end = microtime(true);
        $this->computeTime = ($end - $start) * 1000;
        return $result;
    }

    /**
     * Determines the best available hashing algorithm from a preferred list.
     *
     * @return string
     */
    protected function determineBestAlgo(): string
    {
        $preferred = [
            'xxh3', 'xxh128', 'crc32c', 'xxh64', 'crc32', 'crc32b', 'murmur3f', 'xxh32',
            'murmur3c', 'murmur3a', 'adler32', 'md4', 'fnv1a64', 'fnv132', 'fnv164', 'fnv1a32',
            'joaat', 'md5', 'sha1', 'sha256'
        ];
        $available = hash_algos();
        foreach ($preferred as $algo) {
            if (in_array($algo, $available, true)) {
                return $algo;
            }
        }
        return reset($available);
    }

    /**
     * Validates that the sample size is divisible by 64.
     *
     * @param int $sampleSize
     * @return int
     * @throws InvalidArgumentException
     */
    protected static function preventInvalidSampleSize(int $sampleSize): int
    {
        if ($sampleSize % 64 !== 0) {
            throw new InvalidArgumentException('Sample size must be divisible by 64.');
        }
        return $sampleSize;
    }

    /**
     * Validates the coverage by ensuring it is within the range [1, 100].
     *
     * @param int $coverage
     *
     * @return int
     * @throws InvalidArgumentException
     */
    protected static function preventInvalidCoverage(int $coverage): int
    {
        if ($coverage < 1 || $coverage > 100) {
            throw new InvalidArgumentException('Coverage must be within the range [1, 100].');
        }
        return $coverage;
    }
}
