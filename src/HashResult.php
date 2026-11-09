<?php

declare(strict_types=1);

namespace Mfonte\SparseDigest;

use JsonSerializable;
use Mfonte\SparseDigest\Exception\InvalidArgumentException;

/**
 * HashResult Value Object.
 *
 * Represents the result of a hash computation.
 * Provides conversion methods to hexadecimal, binary and base64 representations,
 * and, when produced by a strategy, records the algorithm used and whether the
 * digest comes from sampled (sparse) or full-file hashing.
 *
 * Instances are immutable and can be safely passed around.
 */
final class HashResult implements JsonSerializable
{
    /** @var string */
    private $binaryValue;

    /** @var string|null */
    private $algorithm;

    /** @var bool */
    private $sparse;

    /**
     * Creates a HashResult from a binary string.
     *
     * @param string $binaryValue The raw binary digest.
     * @param string|null $algorithm The algorithm that produced the digest, if known.
     * @param bool $sparse Whether the digest was computed from sampled blocks (true) or the full file (false).
     * @return self
     */
    public static function new(string $binaryValue, ?string $algorithm = null, bool $sparse = false): self
    {
        return new self($binaryValue, $algorithm, $sparse);
    }

    /**
     * Creates a HashResult from a raw binary digest, e.g. one read back from storage.
     *
     * @param string $binaryValue
     * @param string|null $algorithm
     * @return self
     */
    public static function fromBinary(string $binaryValue, ?string $algorithm = null): self
    {
        return new self($binaryValue, $algorithm, false);
    }

    /**
     * Creates a HashResult from a hexadecimal digest, e.g. one read back from storage.
     *
     * @param string $hex Hexadecimal digest (case-insensitive, even length).
     * @param string|null $algorithm
     * @return self
     * @throws InvalidArgumentException if $hex is not a valid hexadecimal string.
     */
    public static function fromHex(string $hex, ?string $algorithm = null): self
    {
        if ($hex === '' || strlen($hex) % 2 !== 0 || ! ctype_xdigit($hex)) {
            throw new InvalidArgumentException('Invalid hexadecimal digest.');
        }

        return new self((string) hex2bin($hex), $algorithm, false);
    }

    /**
     * Private constructor: use the named constructors.
     *
     * @param string $binaryValue The raw binary value of the hash.
     * @param string|null $algorithm The algorithm that produced the digest, if known.
     * @param bool $sparse Whether the digest was computed from sampled blocks.
     */
    private function __construct(string $binaryValue, ?string $algorithm = null, bool $sparse = false)
    {
        $this->binaryValue = $binaryValue;
        $this->algorithm = $algorithm;
        $this->sparse = $sparse;
    }

    /**
     * Returns the algorithm that produced this digest, or null if unknown
     * (e.g. when the instance was built from a stored value without specifying it).
     *
     * @return string|null
     */
    public function getAlgorithm(): ?string
    {
        return $this->algorithm;
    }

    /**
     * Whether the digest was computed by sampling blocks (true), or by hashing the
     * whole file (false), for example because the file was text or too small to sample.
     *
     * @return bool
     */
    public function isSparse(): bool
    {
        return $this->sparse;
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
     * Returns the base64 representation (RFC 4648, with padding).
     *
     * @return string
     */
    public function toBase64(): string
    {
        return base64_encode($this->binaryValue);
    }

    /**
     * Compares this hash with another one in constant time.
     *
     * Accepts either another HashResult or a hexadecimal string (case-insensitive).
     * Only the digest bytes are compared; algorithm and sparse flag are ignored.
     *
     * @param HashResult|string $other
     * @return bool
     */
    public function equals($other): bool
    {
        if ($other instanceof self) {
            return hash_equals($this->binaryValue, $other->toBinary());
        }

        return hash_equals($this->toHex(), strtolower($other));
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
