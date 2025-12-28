<?php

namespace Mfonte\SparseDigest;

use Mfonte\SparseDigest\Strategy\FullStrategy;
use InvalidArgumentException;

/**
 * FileHasher - Full file hashing facade.
 *
 * Provides a consistent API for computing full-file hashes.
 * Use this when you need to hash the entire file content
 * (e.g., for cryptographic verification or when sparse hashing is not suitable).
 *
 * @phpstan-consistent-constructor
 */
class FileHasher
{
    /** @var HashingStrategy */
    protected $strategy;

    /** @var File */
    protected $file;

    /**
     * Factory method for FileHasher.
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
        $this->strategy = $strategy ?: new FullStrategy();
        $this->file = File::new($file);
    }

    /**
     * Computes the full-file hash using the best available algorithm.
     *
     * @return HashResult
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
     */
    public function hashAs(string $algo): HashResult
    {
        return $this->strategy->hashAs($this->file, $algo);
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
