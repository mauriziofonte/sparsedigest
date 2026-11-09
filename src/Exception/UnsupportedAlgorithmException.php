<?php

declare(strict_types=1);

namespace Mfonte\SparseDigest\Exception;

/**
 * Thrown when a hashing algorithm is not available in hash_algos().
 */
class UnsupportedAlgorithmException extends InvalidArgumentException
{
    /** @var string */
    private $algorithm = '';

    /**
     * @param string $algorithm The rejected algorithm name.
     * @return self
     */
    public static function forAlgorithm(string $algorithm): self
    {
        $exception = new self("Invalid hash algorithm: {$algorithm}");
        $exception->algorithm = $algorithm;

        return $exception;
    }

    /**
     * Returns the rejected algorithm name.
     *
     * @return string
     */
    public function getAlgorithm(): string
    {
        return $this->algorithm;
    }
}
