<?php

declare(strict_types=1);

namespace Mfonte\SparseDigest\Test\Unit\Cases;

use Mfonte\SparseDigest\SparseHasher;
use Mfonte\SparseDigest\Test\Unit\TestCase;

/**
 * Tests for determinism of the SparseDigest hashing.
 *
 * These tests verify that given the same input file and the same parameters,
 * the algorithm always produces the same hash output.
 */
class DeterminismTest extends TestCase
{
    /**
     * Test that multiple calls to hash() on the same SparseDigest instance return the same result.
     */
    public function testMultipleCallsOnSameInstanceReturnSameResult(): void
    {
        $tempFile = $this->createTempFileOfSize(100000, true);
        $sd = SparseHasher::new($tempFile);

        $hash1 = $sd->hash()->toHex();
        $hash2 = $sd->hash()->toHex();
        $hash3 = $sd->hash()->toHex();

        $this->assertSame($hash1, $hash2);
        $this->assertSame($hash2, $hash3);
    }

    /**
     * Test that multiple instances of SparseDigest for the same file return the same hash.
     */
    public function testMultipleInstancesReturnSameHash(): void
    {
        $tempFile = $this->createTempFileOfSize(100000, true);

        $sd1 = SparseHasher::new($tempFile);
        $sd2 = SparseHasher::new($tempFile);
        $sd3 = SparseHasher::new($tempFile);

        $hash1 = $sd1->hash()->toHex();
        $hash2 = $sd2->hash()->toHex();
        $hash3 = $sd3->hash()->toHex();

        $this->assertSame($hash1, $hash2);
        $this->assertSame($hash2, $hash3);
    }

    /**
     * Test that determinism holds for files with specific binary patterns.
     */
    public function testDeterminismWithSpecificBinaryPatterns(): void
    {
        // Create a file with a specific repeating pattern
        $pattern = "\xAB\xCD\xEF\x12\x34\x56\x78\x9A";
        $content = str_repeat($pattern, 12500); // 100,000 bytes

        $tempFile = $this->createTempFileWithContents($content);

        $sd1 = SparseHasher::new($tempFile);
        $sd2 = SparseHasher::new($tempFile);

        $hash1 = $sd1->hash()->toHex();
        $hash2 = $sd2->hash()->toHex();

        $this->assertSame($hash1, $hash2);
    }

    /**
     * Test that determinism holds for files with NULL bytes.
     */
    public function testDeterminismWithNullBytes(): void
    {
        // Create a file with NULL bytes interspersed
        $content = str_repeat("A\x00B\x00C\x00", 20000); // 120,000 bytes

        $tempFile = $this->createTempFileWithContents($content);

        $sd1 = SparseHasher::new($tempFile);
        $sd2 = SparseHasher::new($tempFile);

        $hash1 = $sd1->hash()->toHex();
        $hash2 = $sd2->hash()->toHex();

        $this->assertSame($hash1, $hash2);
    }

    /**
     * Test that determinism holds for text files.
     */
    public function testDeterminismWithTextFiles(): void
    {
        $content = str_repeat("Hello, World! This is a test.\n", 5000);

        $tempFile = $this->createTempFileWithContents($content);

        $sd1 = SparseHasher::new($tempFile);
        $sd2 = SparseHasher::new($tempFile);

        $hash1 = $sd1->hash()->toHex();
        $hash2 = $sd2->hash()->toHex();

        $this->assertSame($hash1, $hash2);
    }

    /**
     * Test that determinism holds across different coverage values.
     */
    public function testDeterminismWithDifferentCoverageValues(): void
    {
        $tempFile = $this->createTempFileOfSize(1000000, true);

        foreach ([5, 10, 20, 50] as $coverage) {
            $sd1 = SparseHasher::new($tempFile);
            $sd2 = SparseHasher::new($tempFile);

            $hash1 = $sd1->hash($coverage, 4096)->toHex();
            $hash2 = $sd2->hash($coverage, 4096)->toHex();

            $this->assertSame($hash1, $hash2, "Determinism failed for coverage={$coverage}");
        }
    }

    /**
     * Test that determinism holds across different sample sizes.
     */
    public function testDeterminismWithDifferentSampleSizes(): void
    {
        $tempFile = $this->createTempFileOfSize(1000000, true);

        foreach ([1024, 2048, 4096, 8192] as $sampleSize) {
            $sd1 = SparseHasher::new($tempFile);
            $sd2 = SparseHasher::new($tempFile);

            $hash1 = $sd1->hash(10, $sampleSize)->toHex();
            $hash2 = $sd2->hash(10, $sampleSize)->toHex();

            $this->assertSame($hash1, $hash2, "Determinism failed for sampleSize={$sampleSize}");
        }
    }

    /**
     * Test that determinism holds for different hashing algorithms.
     */
    public function testDeterminismWithDifferentAlgorithms(): void
    {
        $tempFile = $this->createTempFileOfSize(100000, true);

        foreach (['md5', 'sha1', 'sha256'] as $algo) {
            $sd1 = SparseHasher::new($tempFile);
            $sd2 = SparseHasher::new($tempFile);

            $hash1 = $sd1->hashAs($algo)->toHex();
            $hash2 = $sd2->hashAs($algo)->toHex();

            $this->assertSame($hash1, $hash2, "Determinism failed for algorithm={$algo}");
        }
    }

    /**
     * Test that determinism holds for very small files.
     */
    public function testDeterminismWithSmallFiles(): void
    {
        foreach ([1, 10, 100, 1000] as $size) {
            $content = random_bytes($size);
            $tempFile = $this->createTempFileWithContents($content);

            $sd1 = SparseHasher::new($tempFile);
            $sd2 = SparseHasher::new($tempFile);

            $hash1 = $sd1->hash()->toHex();
            $hash2 = $sd2->hash()->toHex();

            $this->assertSame($hash1, $hash2, "Determinism failed for size={$size}");
        }
    }

    /**
     * Test that determinism holds for files at sampling boundary sizes.
     */
    public function testDeterminismAtSamplingBoundaries(): void
    {
        $sampleSize = 4096;
        $coverage = 10;

        // Test at various boundary sizes
        $boundaries = [
            $sampleSize - 1,
            $sampleSize,
            $sampleSize + 1,
            $sampleSize * 4 - 1,
            $sampleSize * 4,
            $sampleSize * 4 + 1,
            $sampleSize * 14 - 1,
            $sampleSize * 14,
            $sampleSize * 14 + 1,
        ];

        foreach ($boundaries as $size) {
            $content = random_bytes($size);
            $tempFile = $this->createTempFileWithContents($content);

            $sd1 = SparseHasher::new($tempFile);
            $sd2 = SparseHasher::new($tempFile);

            $hash1 = $sd1->hash($coverage, $sampleSize)->toHex();
            $hash2 = $sd2->hash($coverage, $sampleSize)->toHex();

            $this->assertSame($hash1, $hash2, "Determinism failed for boundary size={$size}");
        }
    }

    /**
     * Test that files with only NULL bytes produce deterministic hashes.
     */
    public function testDeterminismWithOnlyNullBytes(): void
    {
        $content = str_repeat("\x00", 100000);
        $tempFile = $this->createTempFileWithContents($content);

        $sd1 = SparseHasher::new($tempFile);
        $sd2 = SparseHasher::new($tempFile);

        $hash1 = $sd1->hash()->toHex();
        $hash2 = $sd2->hash()->toHex();

        $this->assertSame($hash1, $hash2);
    }

    /**
     * Test that files with alternating NULL and data blocks produce deterministic hashes.
     */
    public function testDeterminismWithAlternatingNullBlocks(): void
    {
        // Create a file with alternating 4KB blocks of data and NULL bytes
        $dataBlock = random_bytes(4096);
        $nullBlock = str_repeat("\x00", 4096);
        $content = '';

        for ($i = 0; $i < 25; $i++) {
            $content .= $dataBlock . $nullBlock;
        }

        $tempFile = $this->createTempFileWithContents($content);

        $sd1 = SparseHasher::new($tempFile);
        $sd2 = SparseHasher::new($tempFile);

        $hash1 = $sd1->hash()->toHex();
        $hash2 = $sd2->hash()->toHex();

        $this->assertSame($hash1, $hash2);
    }

    /**
     * Test that determinism is maintained when reading file multiple times.
     */
    public function testDeterminismAcrossMultipleReads(): void
    {
        $tempFile = $this->createTempFileOfSize(500000, true);

        $hashes = [];
        for ($i = 0; $i < 10; $i++) {
            $sd = SparseHasher::new($tempFile);
            $hashes[] = $sd->hash()->toHex();
        }

        // All hashes should be identical
        $uniqueHashes = array_unique($hashes);
        $this->assertCount(1, $uniqueHashes, 'Multiple reads produced different hashes');
    }
}
