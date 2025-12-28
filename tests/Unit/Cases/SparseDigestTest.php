<?php
declare(strict_types=1);

namespace Mfonte\SparseDigest\Test\Unit\Cases;

use Mfonte\SparseDigest\SparseHasher;
use Mfonte\SparseDigest\HashResult;
use Mfonte\SparseDigest\Test\Unit\TestCase;
use InvalidArgumentException;
use SplFileInfo;

/**
 * Tests for the SparseDigest facade class.
 *
 * The SparseDigest class is the main entry point for the library.
 * It provides methods for computing sparse hashes of files.
 */
class SparseDigestTest extends TestCase
{
    /**
     * Test that a SparseDigest can be created from a file path.
     */
    public function testCreateFromFilePath(): void
    {
        $tempFile = $this->createTempFileOfSize(100000, true);
        $sd = SparseHasher::new($tempFile);

        $this->assertInstanceOf(SparseHasher::class, $sd);
    }

    /**
     * Test that a SparseDigest can be created from an SplFileInfo object.
     */
    public function testCreateFromSplFileInfo(): void
    {
        $tempFile = $this->createTempFileOfSize(100000, true);
        $splFileInfo = new SplFileInfo($tempFile);
        $sd = SparseHasher::new($splFileInfo);

        $this->assertInstanceOf(SparseHasher::class, $sd);
    }

    /**
     * Test that creating a SparseDigest with a non-existent file throws an exception.
     */
    public function testCreateWithNonExistentFileThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);

        SparseHasher::new('/path/to/non/existent/file.txt');
    }

    /**
     * Test that hash() returns a Digest object.
     */
    public function testHashReturnsDigest(): void
    {
        $tempFile = $this->createTempFileOfSize(100000, true);
        $sd = SparseHasher::new($tempFile);

        $digest = $sd->hash();

        $this->assertInstanceOf(HashResult::class, $digest);
    }

    /**
     * Test that hash() returns a non-empty hash.
     */
    public function testHashReturnsNonEmptyResult(): void
    {
        $tempFile = $this->createTempFileOfSize(100000, true);
        $sd = SparseHasher::new($tempFile);

        $digest = $sd->hash();

        $this->assertNotEmpty($digest->toHex());
    }

    /**
     * Test that hash() with custom coverage parameter works.
     */
    public function testHashAsCustomCoverage(): void
    {
        $tempFile = $this->createTempFileOfSize(1000000, true);
        $sd = SparseHasher::new($tempFile);

        $digest1 = $sd->hash(10, 4096);
        $digest2 = $sd->hash(20, 4096);

        // Different coverage should produce different hashes
        // (assuming file is large enough to have different samples)
        $this->assertInstanceOf(HashResult::class, $digest1);
        $this->assertInstanceOf(HashResult::class, $digest2);
    }

    /**
     * Test that hash() with custom sample size parameter works.
     */
    public function testHashAsCustomSampleSize(): void
    {
        $tempFile = $this->createTempFileOfSize(1000000, true);
        $sd = SparseHasher::new($tempFile);

        $digest1 = $sd->hash(10, 4096);
        $digest2 = $sd->hash(10, 8192);

        // Different sample sizes should produce different hashes
        $this->assertInstanceOf(HashResult::class, $digest1);
        $this->assertInstanceOf(HashResult::class, $digest2);
    }

    /**
     * Test that hashAs() allows specifying a custom algorithm.
     */
    public function testHashAsCustomAlgorithm(): void
    {
        $tempFile = $this->createTempFileOfSize(100000, true);
        $sd = SparseHasher::new($tempFile);

        $digest = $sd->hashAs('sha256');

        $this->assertInstanceOf(HashResult::class, $digest);
        $this->assertNotEmpty($digest->toHex());
    }

    /**
     * Test that hashAs() with md5 algorithm works correctly.
     */
    public function testHashAsMd5Algorithm(): void
    {
        $tempFile = $this->createTempFileOfSize(100000, true);
        $sd = SparseHasher::new($tempFile);

        $digest = $sd->hashAs('md5');

        // MD5 produces 32 character hex string
        $this->assertSame(32, strlen($digest->toHex()));
    }

    /**
     * Test that hashAs() with sha256 algorithm works correctly.
     */
    public function testHashAsSha256Algorithm(): void
    {
        $tempFile = $this->createTempFileOfSize(100000, true);
        $sd = SparseHasher::new($tempFile);

        $digest = $sd->hashAs('sha256');

        // SHA256 produces 64 character hex string
        $this->assertSame(64, strlen($digest->toHex()));
    }

    /**
     * Test that hashAs() with an invalid algorithm throws an exception.
     * Note: PHP 8+ throws ValueError from hash_file() for invalid algorithms.
     */
    public function testHashAsInvalidAlgorithmThrowsException(): void
    {
        $this->expectException(\Throwable::class);

        $tempFile = $this->createTempFileOfSize(100000, true);
        $sd = SparseHasher::new($tempFile);
        $sd->hashAs('invalid_algorithm');
    }

    /**
     * Test that small files (below sampling threshold) still produce valid hashes.
     */
    public function testSmallFilesProduceValidHashes(): void
    {
        $tempFile = $this->createTempFileOfSize(100, true);
        $sd = SparseHasher::new($tempFile);

        $digest = $sd->hash();

        $this->assertInstanceOf(HashResult::class, $digest);
        $this->assertNotEmpty($digest->toHex());
    }

    /**
     * Test that text files produce valid hashes.
     */
    public function testTextFilesProduceValidHashes(): void
    {
        $tempFile = $this->createTempFileOfSize(100000, false);
        $sd = SparseHasher::new($tempFile);

        $digest = $sd->hash();

        $this->assertInstanceOf(HashResult::class, $digest);
        $this->assertNotEmpty($digest->toHex());
    }

    /**
     * Test that very large files can be hashed.
     */
    public function testLargeFilesCanBeHashed(): void
    {
        // 10MB file
        $tempFile = $this->createTempFileOfSize(10 * 1024 * 1024, true);
        $sd = SparseHasher::new($tempFile);

        $digest = $sd->hash();

        $this->assertInstanceOf(HashResult::class, $digest);
        $this->assertNotEmpty($digest->toHex());
    }

    /**
     * Test that hashing the same file twice produces the same result.
     */
    public function testHashingSameFileTwiceProducesSameResult(): void
    {
        $tempFile = $this->createTempFileOfSize(100000, true);

        $sd1 = SparseHasher::new($tempFile);
        $sd2 = SparseHasher::new($tempFile);

        $digest1 = $sd1->hash();
        $digest2 = $sd2->hash();

        $this->assertSame($digest1->toHex(), $digest2->toHex());
    }

    /**
     * Test that different files produce different hashes.
     */
    public function testDifferentFilesProduceDifferentHashes(): void
    {
        $tempFile1 = $this->createTempFileOfSize(100000, true);
        $tempFile2 = $this->createTempFileOfSize(100000, true);

        $sd1 = SparseHasher::new($tempFile1);
        $sd2 = SparseHasher::new($tempFile2);

        $digest1 = $sd1->hash();
        $digest2 = $sd2->hash();

        // Random files should produce different hashes
        $this->assertNotSame($digest1->toHex(), $digest2->toHex());
    }

    /**
     * Test that files with identical content produce identical hashes.
     */
    public function testIdenticalContentProducesIdenticalHashes(): void
    {
        $content = random_bytes(100000);

        $tempFile1 = $this->createTempFileWithContents($content);
        $tempFile2 = $this->createTempFileWithContents($content);

        $sd1 = SparseHasher::new($tempFile1);
        $sd2 = SparseHasher::new($tempFile2);

        $digest1 = $sd1->hash();
        $digest2 = $sd2->hash();

        $this->assertSame($digest1->toHex(), $digest2->toHex());
    }
}
