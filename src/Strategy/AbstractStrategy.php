<?php

declare(strict_types=1);

namespace Mfonte\SparseDigest\Strategy;

use Mfonte\SparseDigest\Exception\InvalidSamplingParameterException;
use Mfonte\SparseDigest\Exception\RuntimeException;
use Mfonte\SparseDigest\Exception\UnsupportedAlgorithmException;

/**
 * AbstractStrategy - Base class for hashing strategies.
 *
 * This abstract class provides common functionality for all hashing strategies.
 * It cannot be instantiated directly - concrete implementations must extend it.
 */
abstract class AbstractStrategy
{
    /**
     * Algorithms in order of preference (fastest first).
     *
     * @var list<string>
     */
    public const PREFERRED_ALGORITHMS = [
        'xxh3', 'xxh128', 'crc32c', 'xxh64', 'crc32', 'crc32b', 'murmur3f', 'xxh32',
        'murmur3c', 'murmur3a', 'adler32', 'md4', 'fnv1a64', 'fnv132', 'fnv164', 'fnv1a32',
        'joaat', 'md5', 'sha1', 'sha256',
    ];

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
     * @template T
     *
     * @param callable(): T $callback
     * @return T
     */
    protected function stopwatch(callable $callback)
    {
        $start = microtime(true);

        try {
            return $callback();
        } finally {
            $this->computeTime = (microtime(true) - $start) * 1000;
        }
    }

    /**
     * Determines the best available hashing algorithm from a preferred list.
     *
     * @return string
     * @throws RuntimeException if no hashing algorithm is available at all.
     */
    protected function determineBestAlgo(): string
    {
        $available = hash_algos();
        foreach (self::PREFERRED_ALGORITHMS as $algo) {
            if (in_array($algo, $available, true)) {
                return $algo;
            }
        }

        $first = reset($available);
        if ($first === false) {
            throw new RuntimeException('No hashing algorithm is available in this PHP build.');
        }

        return $first;
    }

    /**
     * Validates that the algorithm is supported by the hash extension.
     *
     * @param string $algo
     * @return string
     * @throws UnsupportedAlgorithmException
     */
    protected static function preventInvalidAlgorithm(string $algo): string
    {
        if (! in_array($algo, hash_algos(), true)) {
            throw UnsupportedAlgorithmException::forAlgorithm($algo);
        }

        return $algo;
    }

    /**
     * Validates that the sample size is a positive multiple of 64.
     *
     * @param int $sampleSize
     * @return int
     * @throws InvalidSamplingParameterException
     */
    protected static function preventInvalidSampleSize(int $sampleSize): int
    {
        if ($sampleSize < 64 || $sampleSize % 64 !== 0) {
            throw new InvalidSamplingParameterException("Sample size must be a positive integer divisible by 64, {$sampleSize} given.");
        }

        return $sampleSize;
    }

    /**
     * Validates the coverage by ensuring it is within the range [1, 100].
     *
     * @param int $coverage
     *
     * @return int
     * @throws InvalidSamplingParameterException
     */
    protected static function preventInvalidCoverage(int $coverage): int
    {
        if ($coverage < 1 || $coverage > 100) {
            throw new InvalidSamplingParameterException("Coverage must be within the range [1, 100], {$coverage} given.");
        }

        return $coverage;
    }
}
