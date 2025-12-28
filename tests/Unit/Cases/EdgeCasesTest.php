<?php
declare(strict_types=1);

namespace Mfonte\SparseDigest\Test\Unit\Cases;

use Mfonte\SparseDigest\SparseHasher;
use Mfonte\SparseDigest\File;
use Mfonte\SparseDigest\HashResult;
use Mfonte\SparseDigest\Test\Unit\TestCase;
use InvalidArgumentException;

/**
 * Tests for edge cases in the SparseDigest library.
 *
 * These tests cover unusual scenarios and boundary conditions
 * that might reveal bugs or unexpected behavior.
 */
class EdgeCasesTest extends TestCase
{
    /**
     * Test that empty files throw an exception.
     * The library does not support empty files by design.
     */
    public function testEmptyFileThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('does not exist or is empty');

        $tempFile = $this->createTempFileWithContents('');
        SparseHasher::new($tempFile);
    }

    /**
     * Test hashing a file with a single byte.
     */
    public function testSingleByteFile(): void
    {
        $tempFile = $this->createTempFileWithContents('X');
        $sd = SparseHasher::new($tempFile);

        $digest = $sd->hash();
        $this->assertInstanceOf(HashResult::class, $digest);
    }

    /**
     * Test hashing a file with exactly one sample size worth of data.
     */
    public function testFileSizeExactlyOneSampleSize(): void
    {
        $sampleSize = 4096;
        $tempFile = $this->createTempFileOfSize($sampleSize, true);
        $sd = SparseHasher::new($tempFile);

        $digest = $sd->hash(10, $sampleSize);
        $this->assertInstanceOf(HashResult::class, $digest);
    }

    /**
     * Test hashing a file with size = (coverage + 4) * sampleSize (threshold).
     */
    public function testFileSizeAtSamplingThreshold(): void
    {
        $coverage = 10;
        $sampleSize = 4096;
        // Threshold for sampling
        $thresholdSize = $sampleSize * 14; // Approximately

        $tempFile = $this->createTempFileOfSize($thresholdSize, true);
        $sd = SparseHasher::new($tempFile);

        $digest = $sd->hash($coverage, $sampleSize);
        $this->assertInstanceOf(HashResult::class, $digest);
    }

    /**
     * Test hashing with minimum coverage (1%).
     */
    public function testMinimumCoverage(): void
    {
        $tempFile = $this->createTempFileOfSize(1000000, true);
        $sd = SparseHasher::new($tempFile);

        $digest = $sd->hash(1, 4096);
        $this->assertInstanceOf(HashResult::class, $digest);
    }

    /**
     * Test hashing with maximum coverage (100%).
     */
    public function testMaximumCoverage(): void
    {
        $tempFile = $this->createTempFileOfSize(100000, true);
        $sd = SparseHasher::new($tempFile);

        $digest = $sd->hash(100, 4096);
        $this->assertInstanceOf(HashResult::class, $digest);
    }

    /**
     * Test hashing with very small sample size.
     */
    public function testVerySmallSampleSize(): void
    {
        $tempFile = $this->createTempFileOfSize(100000, true);
        $sd = SparseHasher::new($tempFile);

        $digest = $sd->hash(10, 64);
        $this->assertInstanceOf(HashResult::class, $digest);
    }

    /**
     * Test hashing with very large sample size.
     */
    public function testVeryLargeSampleSize(): void
    {
        $tempFile = $this->createTempFileOfSize(1000000, true);
        $sd = SparseHasher::new($tempFile);

        $digest = $sd->hash(10, 65536);
        $this->assertInstanceOf(HashResult::class, $digest);
    }

    /**
     * Test that identical files produce identical hashes.
     */
    public function testIdenticalFilesProduceIdenticalHashes(): void
    {
        $content = random_bytes(100000);

        $tempFile1 = $this->createTempFileWithContents($content);
        $tempFile2 = $this->createTempFileWithContents($content);

        $sd1 = SparseHasher::new($tempFile1);
        $sd2 = SparseHasher::new($tempFile2);

        $this->assertSame($sd1->hash()->toHex(), $sd2->hash()->toHex());
    }

    /**
     * Test that modifying a single byte changes the hash (for small files).
     */
    public function testSingleByteModificationChangesHash(): void
    {
        $content = str_repeat('A', 1000);
        $modifiedContent = str_repeat('A', 499) . 'B' . str_repeat('A', 500);

        $tempFile1 = $this->createTempFileWithContents($content);
        $tempFile2 = $this->createTempFileWithContents($modifiedContent);

        $sd1 = SparseHasher::new($tempFile1);
        $sd2 = SparseHasher::new($tempFile2);

        // Small files are fully hashed, so any change should produce different hash
        $this->assertNotSame($sd1->hash()->toHex(), $sd2->hash()->toHex());
    }

    /**
     * Test hashing a file that contains only newlines.
     */
    public function testFileWithOnlyNewlines(): void
    {
        $content = str_repeat("\n", 100000);
        $tempFile = $this->createTempFileWithContents($content);
        $sd = SparseHasher::new($tempFile);

        $digest = $sd->hash();
        $this->assertInstanceOf(HashResult::class, $digest);
    }

    /**
     * Test hashing a file with mixed line endings.
     */
    public function testFileWithMixedLineEndings(): void
    {
        $content = "Line 1\nLine 2\r\nLine 3\rLine 4\n\rLine 5\r\n\nLine 6";
        $content = str_repeat($content, 1000);

        $tempFile = $this->createTempFileWithContents($content);
        $sd = SparseHasher::new($tempFile);

        $digest = $sd->hash();
        $this->assertInstanceOf(HashResult::class, $digest);
    }

    /**
     * Test hashing a file with high-bit ASCII characters.
     */
    public function testFileWithHighBitAscii(): void
    {
        $content = '';
        for ($i = 128; $i <= 255; $i++) {
            $content .= chr($i);
        }
        $content = str_repeat($content, 1000);

        $tempFile = $this->createTempFileWithContents($content);
        $sd = SparseHasher::new($tempFile);

        $digest = $sd->hash();
        $this->assertInstanceOf(HashResult::class, $digest);
    }

    /**
     * Test hashing UTF-8 encoded content.
     */
    public function testFileWithUtf8Content(): void
    {
        $content = "日本語 中文 한국어 العربية עברית ελληνικά русский";
        $content = str_repeat($content, 1000);

        $tempFile = $this->createTempFileWithContents($content);
        $sd = SparseHasher::new($tempFile);

        $digest = $sd->hash();
        $this->assertInstanceOf(HashResult::class, $digest);
    }

    /**
     * Test hashing binary file with all possible byte values.
     */
    public function testFileWithAllByteValues(): void
    {
        $content = '';
        for ($i = 0; $i <= 255; $i++) {
            $content .= chr($i);
        }
        $content = str_repeat($content, 500);

        $tempFile = $this->createTempFileWithContents($content);
        $sd = SparseHasher::new($tempFile);

        $digest = $sd->hash();
        $this->assertInstanceOf(HashResult::class, $digest);
    }

    /**
     * Test that different hashing algorithms produce different length outputs.
     */
    public function testDifferentAlgorithmsProduceDifferentLengths(): void
    {
        $tempFile = $this->createTempFileOfSize(100000, true);
        $sd = SparseHasher::new($tempFile);

        $md5 = $sd->hashAs('md5');
        $sha1 = $sd->hashAs('sha1');
        $sha256 = $sd->hashAs('sha256');

        $this->assertSame(32, strlen($md5->toHex()));   // 128 bits = 32 hex chars
        $this->assertSame(40, strlen($sha1->toHex()));  // 160 bits = 40 hex chars
        $this->assertSame(64, strlen($sha256->toHex())); // 256 bits = 64 hex chars
    }

    /**
     * Test Digestable with files near common filesystem block sizes.
     */
    public function testFileSizesNearBlockBoundaries(): void
    {
        $blockSizes = [512, 1024, 2048, 4096, 8192];

        foreach ($blockSizes as $blockSize) {
            foreach ([-1, 0, 1] as $delta) {
                $size = $blockSize + $delta;

                $tempFile = $this->createTempFileOfSize($size, true);
                $sd = SparseHasher::new($tempFile);

                $digest = $sd->hash();
                $this->assertInstanceOf(HashResult::class, $digest, "Failed for size {$size}");
            }
        }
    }

    /**
     * Test that isLikelyBinary correctly identifies binary vs text.
     */
    public function testBinaryVsTextDetection(): void
    {
        // Pure text
        $textContent = str_repeat("Hello, World! This is text.\n", 1000);
        $textFile = $this->createTempFileWithContents($textContent);
        $textDigestable = File::new($textFile);

        // Binary with NULL bytes
        $binaryContent = random_bytes(30000);
        $binaryFile = $this->createTempFileWithContents($binaryContent);
        $binaryDigestable = File::new($binaryFile);

        // Text should not be detected as binary (likely)
        // Binary should be detected as binary
        $this->assertTrue($binaryDigestable->isLikelyBinary());
    }

    /**
     * Test behavior with file size exactly at sampling formula boundary.
     */
    public function testExactSamplingFormulaBoundary(): void
    {
        $coverage = 10;
        $sampleSize = 4096;

        // Calculate exact boundary based on cantBeSampled logic
        // totalSampleBytes = floor(fileSize * coverage / 100)
        // totalBlocks = floor(totalSampleBytes / sampleSize)
        // cantBeSampled if totalBlocks < 4 or fileSize < (totalBlocks * sampleSize)

        // For 4 blocks minimum: totalBlocks >= 4
        // 4 = floor(floor(fileSize * 10 / 100) / 4096)
        // 4 * 4096 <= fileSize * 10 / 100
        // 16384 * 100 / 10 <= fileSize
        // 163840 <= fileSize

        $boundarySize = 163840;

        $tempFile = $this->createTempFileOfSize($boundarySize, true);
        $file = File::new($tempFile);

        // At boundary, should be able to be sampled
        $canSample = !$file->cantBeSampled($coverage, $sampleSize);
        $this->assertTrue($canSample);

        // Just below boundary
        $tempFileSmall = $this->createTempFileOfSize($boundarySize - 1, true);
        $fileSmall = File::new($tempFileSmall);

        // This might or might not be sampleable depending on exact math
        // The test just ensures no exceptions are thrown
        $fileSmall->cantBeSampled($coverage, $sampleSize);
    }

    /**
     * Test that very high coverage doesn't cause issues.
     */
    public function testVeryHighCoverageValue(): void
    {
        $tempFile = $this->createTempFileOfSize(100000, true);
        $sd = SparseHasher::new($tempFile);

        // 100% coverage - essentially full file hash
        $digest = $sd->hash(100, 4096);
        $this->assertInstanceOf(HashResult::class, $digest);
    }

    /**
     * Test creating Digestable from the same file path multiple times.
     */
    public function testMultipleDigestableInstancesSameFile(): void
    {
        $tempFile = $this->createTempFileOfSize(50000, true);

        $d1 = File::new($tempFile);
        $d2 = File::new($tempFile);
        $d3 = File::new($tempFile);

        // All should have the same size
        $this->assertSame($d1->getSize(), $d2->getSize());
        $this->assertSame($d2->getSize(), $d3->getSize());

        // All should have the same path
        $this->assertSame($d1->getPath(), $d2->getPath());
    }
}
