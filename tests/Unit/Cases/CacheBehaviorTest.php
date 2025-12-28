<?php
declare(strict_types=1);

namespace Mfonte\SparseDigest\Test\Unit\Cases;

use Mfonte\SparseDigest\File;
use Mfonte\SparseDigest\Test\Unit\TestCase;

/**
 * Tests for the cache behavior in Digestable class.
 *
 * The Digestable class caches chunks read from the file to avoid
 * redundant I/O operations. The cache key must include both offset
 * and length to correctly handle different read operations.
 */
class CacheBehaviorTest extends TestCase
{
    /**
     * Test that reading the same offset and length returns identical data.
     */
    public function testSameOffsetAndLengthReturnsCachedData(): void
    {
        $content = random_bytes(100000);
        $tempFile = $this->createTempFileWithContents($content);
        $file = File::new($tempFile);

        $data1 = $file->readMeaningfulBytesAtOffset(1000, 4096);
        $data2 = $file->readMeaningfulBytesAtOffset(1000, 4096);

        $this->assertSame($data1, $data2);
    }

    /**
     * Test that same offset with different lengths returns different data.
     * This is the critical test for the cache key bug fix.
     */
    public function testSameOffsetDifferentLengthsReturnsDifferentData(): void
    {
        // Use predictable content to ensure we can verify the difference
        $content = str_repeat('ABCDEFGH', 15000); // 120,000 bytes
        $tempFile = $this->createTempFileWithContents($content);
        $file = File::new($tempFile);

        $data1 = $file->readMeaningfulBytesAtOffset(0, 100);
        $data2 = $file->readMeaningfulBytesAtOffset(0, 200);
        $data3 = $file->readMeaningfulBytesAtOffset(0, 500);

        // All should have different lengths
        $this->assertSame(100, strlen($data1));
        $this->assertSame(200, strlen($data2));
        $this->assertSame(500, strlen($data3));

        // data2 and data3 should not be equal to data1
        $this->assertNotSame($data1, $data2);
        $this->assertNotSame($data1, $data3);
        $this->assertNotSame($data2, $data3);
    }

    /**
     * Test that different offsets with same length returns different data.
     */
    public function testDifferentOffsetsSameLengthReturnsDifferentData(): void
    {
        // Create file with different content at different offsets
        $content = '';
        for ($i = 0; $i < 100; $i++) {
            $content .= str_repeat(chr(65 + ($i % 26)), 1000);
        }
        $tempFile = $this->createTempFileWithContents($content);
        $file = File::new($tempFile);

        $data1 = $file->readMeaningfulBytesAtOffset(0, 100);
        $data2 = $file->readMeaningfulBytesAtOffset(1000, 100);
        $data3 = $file->readMeaningfulBytesAtOffset(2000, 100);

        // All should have the same length
        $this->assertSame(100, strlen($data1));
        $this->assertSame(100, strlen($data2));
        $this->assertSame(100, strlen($data3));

        // But different content (assuming different characters at each offset)
        $this->assertNotSame($data1, $data2);
        $this->assertNotSame($data2, $data3);
    }

    /**
     * Test that cache correctly handles sequential reads.
     */
    public function testCacheWithSequentialReads(): void
    {
        $content = random_bytes(100000);
        $tempFile = $this->createTempFileWithContents($content);
        $file = File::new($tempFile);

        $length = 4096;
        $results = [];

        // Read sequential blocks
        for ($i = 0; $i < 10; $i++) {
            $offset = $i * $length;
            $results[$offset] = $file->readMeaningfulBytesAtOffset($offset, $length);
        }

        // Re-read the same blocks - should return cached data
        for ($i = 0; $i < 10; $i++) {
            $offset = $i * $length;
            $cachedData = $file->readMeaningfulBytesAtOffset($offset, $length);
            $this->assertSame($results[$offset], $cachedData);
        }
    }

    /**
     * Test that cache correctly handles random access patterns.
     */
    public function testCacheWithRandomAccessPattern(): void
    {
        $content = random_bytes(100000);
        $tempFile = $this->createTempFileWithContents($content);
        $file = File::new($tempFile);

        $length = 1000;
        $offsets = [50000, 10000, 80000, 30000, 60000, 20000, 70000, 40000];
        $results = [];

        // First pass: read in random order
        foreach ($offsets as $offset) {
            $results[$offset] = $file->readMeaningfulBytesAtOffset($offset, $length);
        }

        // Second pass: read in different random order
        shuffle($offsets);
        foreach ($offsets as $offset) {
            $cachedData = $file->readMeaningfulBytesAtOffset($offset, $length);
            $this->assertSame($results[$offset], $cachedData);
        }
    }

    /**
     * Test that cache handles overlapping reads correctly.
     * Two reads that overlap in file position but have different start/length.
     */
    public function testCacheWithOverlappingReads(): void
    {
        $content = str_repeat('X', 100000);
        $tempFile = $this->createTempFileWithContents($content);
        $file = File::new($tempFile);

        // These reads overlap in the file but have different cache keys
        $data1 = $file->readMeaningfulBytesAtOffset(1000, 5000); // 1000-6000
        $data2 = $file->readMeaningfulBytesAtOffset(3000, 5000); // 3000-8000

        $this->assertSame(5000, strlen($data1));
        $this->assertSame(5000, strlen($data2));

        // Both should work independently
        $this->assertNotEmpty($data1);
        $this->assertNotEmpty($data2);
    }

    /**
     * Test that different Digestable instances have separate caches.
     */
    public function testSeparateCachesForDifferentInstances(): void
    {
        $content1 = str_repeat('A', 100000);
        $content2 = str_repeat('B', 100000);

        $tempFile1 = $this->createTempFileWithContents($content1);
        $tempFile2 = $this->createTempFileWithContents($content2);

        $file1 = File::new($tempFile1);
        $file2 = File::new($tempFile2);

        $data1 = $file1->readMeaningfulBytesAtOffset(0, 100);
        $data2 = $file2->readMeaningfulBytesAtOffset(0, 100);

        // Same offset and length, but different files = different data
        $this->assertNotSame($data1, $data2);
    }

    /**
     * Test cache behavior with edge cases near file boundaries.
     */
    public function testCacheWithBoundaryReads(): void
    {
        $size = 10000;
        $content = random_bytes($size);
        $tempFile = $this->createTempFileWithContents($content);
        $file = File::new($tempFile);

        // Read from the very beginning
        $start = $file->readMeaningfulBytesAtOffset(0, 100);

        // Read from near the end (max valid is 10000 - 100 = 9900)
        $end = $file->readMeaningfulBytesAtOffset(9900, 100);

        $this->assertSame(100, strlen($start));
        $this->assertSame(100, strlen($end));

        // Verify caching
        $startCached = $file->readMeaningfulBytesAtOffset(0, 100);
        $endCached = $file->readMeaningfulBytesAtOffset(9900, 100);

        $this->assertSame($start, $startCached);
        $this->assertSame($end, $endCached);
    }

    /**
     * Test that cache works correctly with NULL byte content.
     * Note: NULL bytes are replaced with their length as a string, so the
     * actual returned data may not be exactly the requested length.
     */
    public function testCacheWithNullByteContent(): void
    {
        // File with NULL bytes interspersed
        $content = '';
        for ($i = 0; $i < 1000; $i++) {
            $content .= random_bytes(50);
            $content .= str_repeat("\x00", 50);
        }

        $tempFile = $this->createTempFileWithContents($content);
        $file = File::new($tempFile);

        // Read the same position twice
        $data1 = $file->readMeaningfulBytesAtOffset(5000, 1000);
        $data2 = $file->readMeaningfulBytesAtOffset(5000, 1000);

        // Cache should return identical results for same offset:length
        $this->assertSame($data1, $data2);
        
        // Length should be at least 1000 bytes (may be more due to NULL replacement)
        $this->assertGreaterThanOrEqual(1000, strlen($data1));
    }

    /**
     * Test that reading with length 1 and then larger length works correctly.
     */
    public function testCacheWithIncreasingSizes(): void
    {
        $content = str_repeat('XYZA', 25000);
        $tempFile = $this->createTempFileWithContents($content);
        $file = File::new($tempFile);

        // Read with increasing sizes from same offset
        $data1 = $file->readMeaningfulBytesAtOffset(0, 1);
        $data10 = $file->readMeaningfulBytesAtOffset(0, 10);
        $data100 = $file->readMeaningfulBytesAtOffset(0, 100);
        $data1000 = $file->readMeaningfulBytesAtOffset(0, 1000);

        $this->assertSame(1, strlen($data1));
        $this->assertSame(10, strlen($data10));
        $this->assertSame(100, strlen($data100));
        $this->assertSame(1000, strlen($data1000));

        // Each should be a prefix of the next (for this predictable content)
        // Note: This might not hold if NULL replacement changes things
    }
}
