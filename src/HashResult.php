<?php

namespace Mfonte\SparseDigest;

use JsonSerializable;

/**
 * HashResult Value Object.
 *
 * Represents the result of a hash computation.
 * Provides conversion methods to hexadecimal and binary representations.
 *
 * This class is immutable and can be safely passed around.
 *
 * @phpstan-consistent-constructor
 */
class HashResult implements JsonSerializable
{
    /** @var string */
    protected $binaryValue;

    /**
     * Creates a HashResult from a binary string.
     *
     * @param string $binaryValue
     * @return static
     */
    public static function new(string $binaryValue): self
    {
        return new static($binaryValue);
    }

    /**
     * Protected constructor to allow inheritance.
     *
     * @param string $binaryValue The raw binary value of the hash.
     */
    protected function __construct(string $binaryValue)
    {
        $this->binaryValue = $binaryValue;
    }

    /**
     * Returns the binary representation.
     *
     * @return string
     */
    public function toBinary(): string
    {
        return $this->binaryValue;
    }

    /**
     * Returns the hexadecimal representation.
     *
     * @return string
     */
    public function toHex(): string
    {
        return bin2hex($this->binaryValue);
    }

    /**
     * Returns the string representation.
     *
     * @return string
     */
    public function toString(): string
    {
        return $this->toHex();
    }

    /**
     * String representation.
     *
     * @return string
     */
    public function __toString(): string
    {
        return $this->toHex();
    }

    /**
     * Specify data for JSON serialization.
     *
     * @return string
     */
    public function jsonSerialize(): string
    {
        return $this->__toString();
    }
}
