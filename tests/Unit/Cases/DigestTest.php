<?php
declare(strict_types=1);

namespace Mfonte\SparseDigest\Test\Unit\Cases;

use Mfonte\SparseDigest\HashResult;
use Mfonte\SparseDigest\Test\Unit\TestCase;

/**
 * Tests for the Digest value object class.
 *
 * The Digest class represents the result of a hash computation
 * and provides conversion methods between binary and hex representations.
 */
class DigestTest extends TestCase
{
    /**
     * Test that a Digest can be created from a binary string.
     */
    public function testCreateFromBinaryString(): void
    {
        $binary = random_bytes(32);
        $digest = HashResult::new($binary);

        $this->assertInstanceOf(HashResult::class, $digest);
    }

    /**
     * Test that toBinary() returns the original binary value.
     */
    public function testToBinaryReturnsOriginalValue(): void
    {
        $binary = random_bytes(32);
        $digest = HashResult::new($binary);

        $this->assertSame($binary, $digest->toBinary());
    }

    /**
     * Test that toHex() returns the correct hexadecimal representation.
     */
    public function testToHexReturnsCorrectValue(): void
    {
        $binary = "\x00\x01\x02\x03\x04\x05\x06\x07\x08\x09\x0a\x0b\x0c\x0d\x0e\x0f";
        $digest = HashResult::new($binary);

        $this->assertSame('000102030405060708090a0b0c0d0e0f', $digest->toHex());
    }

    /**
     * Test that toString() returns the hex representation.
     */
    public function testToStringReturnsHex(): void
    {
        $binary = "\xde\xad\xbe\xef";
        $digest = HashResult::new($binary);

        $this->assertSame('deadbeef', $digest->toString());
    }

    /**
     * Test that __toString() magic method works correctly.
     */
    public function testMagicToStringReturnsHex(): void
    {
        $binary = "\xca\xfe\xba\xbe";
        $digest = HashResult::new($binary);

        $this->assertSame('cafebabe', (string) $digest);
    }

    /**
     * Test that jsonSerialize() returns the hex string for JSON encoding.
     */
    public function testJsonSerializeReturnsHex(): void
    {
        $binary = "\x12\x34\x56\x78";
        $digest = HashResult::new($binary);

        $this->assertSame('12345678', $digest->jsonSerialize());
        $this->assertSame('"12345678"', json_encode($digest));
    }

    /**
     * Test that two Digests with the same binary value produce the same hex.
     */
    public function testIdenticalBinaryProducesIdenticalHex(): void
    {
        $binary = random_bytes(64);
        $digest1 = HashResult::new($binary);
        $digest2 = HashResult::new($binary);

        $this->assertSame($digest1->toHex(), $digest2->toHex());
        $this->assertSame($digest1->toBinary(), $digest2->toBinary());
    }

    /**
     * Test that different binary values produce different hex strings.
     */
    public function testDifferentBinaryProducesDifferentHex(): void
    {
        $binary1 = random_bytes(32);
        $binary2 = random_bytes(32);

        // Extremely unlikely to be equal, but let's ensure they're different
        while ($binary1 === $binary2) {
            $binary2 = random_bytes(32);
        }

        $digest1 = HashResult::new($binary1);
        $digest2 = HashResult::new($binary2);

        $this->assertNotSame($digest1->toHex(), $digest2->toHex());
    }

    /**
     * Test that an empty binary string produces an empty hex string.
     */
    public function testEmptyBinaryProducesEmptyHex(): void
    {
        $digest = HashResult::new('');

        $this->assertSame('', $digest->toBinary());
        $this->assertSame('', $digest->toHex());
    }

    /**
     * Test that binary with all null bytes produces correct hex.
     */
    public function testNullBytesProduceCorrectHex(): void
    {
        $binary = "\x00\x00\x00\x00";
        $digest = HashResult::new($binary);

        $this->assertSame('00000000', $digest->toHex());
    }

    /**
     * Test that binary with all 0xFF bytes produces correct hex.
     */
    public function testMaxBytesProduceCorrectHex(): void
    {
        $binary = "\xff\xff\xff\xff";
        $digest = HashResult::new($binary);

        $this->assertSame('ffffffff', $digest->toHex());
    }

    /**
     * Test round-trip conversion: hex -> binary -> hex.
     */
    public function testRoundTripConversion(): void
    {
        $originalHex = 'abcdef0123456789';
        $binary = hex2bin($originalHex);
        $digest = HashResult::new($binary);

        $this->assertSame($originalHex, $digest->toHex());
    }
}
