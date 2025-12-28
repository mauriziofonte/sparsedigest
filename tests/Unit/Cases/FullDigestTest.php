<?php
namespace Mfonte\SparseDigest\Test\Unit\Cases;

use Mfonte\SparseDigest\FileHasher;
use Mfonte\SparseDigest\HashResult;
use Mfonte\SparseDigest\Test\Unit\TestCase;
use InvalidArgumentException;

/**
 * Tests for the FullDigest class.
 */
class FullDigestTest extends TestCase
{
    /**
     * Test that FullDigest can be instantiated with a valid file path.
     */
    public function testCanBeInstantiatedWithFilePath(): void
    {
        $tempFile = $this->createTempFileOfSize(1000, true);
        $fd = FileHasher::new($tempFile);
        
        $this->assertInstanceOf(FileHasher::class, $fd);
    }

    /**
     * Test that FullDigest throws exception for non-existent file.
     */
    public function testThrowsExceptionForNonExistentFile(): void
    {
        $this->expectException(InvalidArgumentException::class);
        
        FileHasher::new('/non/existent/file.bin');
    }

    /**
     * Test that hash() returns a Digest object.
     */
    public function testHashReturnsDigest(): void
    {
        $tempFile = $this->createTempFileOfSize(1000, true);
        $fd = FileHasher::new($tempFile);
        
        $digest = $fd->hash();
        
        $this->assertInstanceOf(HashResult::class, $digest);
        $this->assertNotEmpty($digest->toHex());
    }

    /**
     * Test that hashAs() returns a Digest with the correct algorithm.
     */
    public function testHashAsReturnsDigestWithCorrectAlgorithm(): void
    {
        $tempFile = $this->createTempFileOfSize(1000, true);
        $fd = FileHasher::new($tempFile);
        
        $md5Digest = $fd->hashAs('md5');
        $sha256Digest = $fd->hashAs('sha256');
        
        // MD5 = 32 hex chars, SHA256 = 64 hex chars
        $this->assertSame(32, strlen($md5Digest->toHex()));
        $this->assertSame(64, strlen($sha256Digest->toHex()));
    }

    /**
     * Test that hashAs() throws exception for invalid algorithm.
     */
    public function testHashAsThrowsExceptionForInvalidAlgorithm(): void
    {
        $this->expectException(InvalidArgumentException::class);
        
        $tempFile = $this->createTempFileOfSize(1000, true);
        $fd = FileHasher::new($tempFile);
        $fd->hashAs('invalid_algorithm');
    }

    /**
     * Test that FullDigest produces consistent results.
     */
    public function testProducesConsistentResults(): void
    {
        $content = random_bytes(10000);
        $tempFile = $this->createTempFileWithContents($content);
        
        $fd1 = FileHasher::new($tempFile);
        $fd2 = FileHasher::new($tempFile);
        
        $hash1 = $fd1->hash()->toHex();
        $hash2 = $fd2->hash()->toHex();
        
        $this->assertSame($hash1, $hash2);
    }

    /**
     * Test that FullDigest matches native hash_file.
     */
    public function testMatchesNativeHashFile(): void
    {
        $content = random_bytes(10000);
        $tempFile = $this->createTempFileWithContents($content);
        
        $fd = FileHasher::new($tempFile);
        $digest = $fd->hashAs('sha256');
        
        $nativeHash = hash_file('sha256', $tempFile);
        
        $this->assertSame($nativeHash, $digest->toHex());
    }

    /**
     * Test that different files produce different hashes.
     */
    public function testDifferentFilesProduceDifferentHashes(): void
    {
        $tempFile1 = $this->createTempFileOfSize(1000, true);
        $tempFile2 = $this->createTempFileOfSize(1000, true);
        
        $fd1 = FileHasher::new($tempFile1);
        $fd2 = FileHasher::new($tempFile2);
        
        $hash1 = $fd1->hash()->toHex();
        $hash2 = $fd2->hash()->toHex();
        
        $this->assertNotSame($hash1, $hash2);
    }

    /**
     * Test that getComputeTime returns a positive value after hashing.
     */
    public function testGetComputeTimeReturnsPositiveValue(): void
    {
        $tempFile = $this->createTempFileOfSize(10000, true);
        $fd = FileHasher::new($tempFile);
        
        $fd->hash();
        
        $this->assertGreaterThan(0, $fd->getComputeTime());
    }

    /**
     * Test that FullDigest works with SplFileInfo.
     */
    public function testWorksWithSplFileInfo(): void
    {
        $tempFile = $this->createTempFileOfSize(1000, true);
        $fileInfo = new \SplFileInfo($tempFile);
        
        $fd = FileHasher::new($fileInfo);
        $digest = $fd->hash();
        
        $this->assertInstanceOf(HashResult::class, $digest);
    }
}
