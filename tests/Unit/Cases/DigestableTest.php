<?php

declare(strict_types=1);

namespace Mfonte\SparseDigest\Test\Unit\Cases;

use InvalidArgumentException;
use Mfonte\SparseDigest\File;
use Mfonte\SparseDigest\HashResult;
use Mfonte\SparseDigest\Test\Unit\TestCase;
use SplFileInfo;

/**
 * Tests for the Digestable class.
 *
 * The Digestable class wraps file operations and provides methods for:
 * - File metadata (size, path, MIME type)
 * - Reading meaningful bytes at specific offsets
 * - Determining if a file can be sampled
 * - Full-file hashing
 */
class DigestableTest extends TestCase
{
    /**
     * Test that a Digestable can be created from a file path string.
     */
    public function testCreateFromFilePath(): void
    {
        $tempFile = $this->createTempFileOfSize(1024, true);
        $file = File::new($tempFile);

        $this->assertInstanceOf(File::class, $file);
    }

    /**
     * Test that a Digestable can be created from an SplFileInfo object.
     */
    public function testCreateFromSplFileInfo(): void
    {
        $tempFile = $this->createTempFileOfSize(1024, true);
        $splFileInfo = new SplFileInfo($tempFile);
        $file = File::new($splFileInfo);

        $this->assertInstanceOf(File::class, $file);
    }

    /**
     * Test that creating a Digestable with a non-existent file throws an exception.
     */
    public function testCreateWithNonExistentFileThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('does not exist');

        File::new('/path/to/non/existent/file.txt');
    }

    /**
     * Test that creating a Digestable with a directory throws an exception.
     */
    public function testCreateWithDirectoryThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);

        File::new(sys_get_temp_dir());
    }

    /**
     * Test that creating a Digestable with an invalid type throws an exception.
     */
    public function testCreateWithInvalidTypeThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid file type');

        /** @phpstan-ignore-next-line Testing invalid type intentionally */
        File::new(['not', 'a', 'file']);
    }

    /**
     * Test that getPath() returns the correct file path.
     */
    public function testGetPathReturnsCorrectPath(): void
    {
        $tempFile = $this->createTempFileOfSize(1024, true);
        $file = File::new($tempFile);

        $this->assertSame($tempFile, $file->getPath());
    }

    /**
     * Test that getSize() returns the correct file size.
     */
    public function testGetSizeReturnsCorrectSize(): void
    {
        $size = 12345;
        $tempFile = $this->createTempFileOfSize($size, true);
        $file = File::new($tempFile);

        $this->assertSame($size, $file->getSize());
    }

    /**
     * Test that getSize() returns 0 for an empty file.
     */
    public function testGetSizeReturnsZeroForEmptyFile(): void
    {
        $file = File::new($this->createTempFileWithContents(''));

        $this->assertSame(0, $file->getSize());
        $this->assertSame(hash('sha256', '', true), $file->hashWith('sha256')->toBinary());
    }

    /**
     * Test that getMimeType() returns a valid MIME type.
     */
    public function testGetMimeTypeReturnsValidType(): void
    {
        $tempFile = $this->createTempFileWithContents('<?php echo "Hello World";');
        $file = File::new($tempFile);

        $mimeType = $file->getMimeType();
        $this->assertIsString($mimeType);
        $this->assertNotEmpty($mimeType);
    }

    /**
     * Test that hashWith() returns a Digest object.
     */
    public function testHashWithReturnsDigest(): void
    {
        $tempFile = $this->createTempFileOfSize(1024, true);
        $file = File::new($tempFile);

        $digest = $file->hashWith('sha256');

        $this->assertInstanceOf(HashResult::class, $digest);
    }

    /**
     * Test that hashWith() with an invalid algorithm throws an exception.
     */
    public function testHashWithInvalidAlgorithmThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid hash algorithm');

        $tempFile = $this->createTempFileOfSize(1024, true);
        $file = File::new($tempFile);
        $file->hashWith('invalid_algo');
    }

    /**
     * Test that hashWith() produces consistent results.
     */
    public function testHashWithProducesConsistentResults(): void
    {
        $contents = 'Hello, World! This is a test file.';
        $tempFile = $this->createTempFileWithContents($contents);
        $file = File::new($tempFile);

        $hash1 = $file->hashWith('sha256');
        $hash2 = $file->hashWith('sha256');

        $this->assertSame($hash1->toHex(), $hash2->toHex());

        // Verify against PHP's native hash_file
        $expected = hash_file('sha256', $tempFile);
        $this->assertSame($expected, $hash1->toHex());
    }

    /**
     * Test that cantBeSampled() returns true for small files.
     */
    public function testCantBeSampledReturnsTrueForSmallFiles(): void
    {
        // A file smaller than the minimum required for sampling
        $tempFile = $this->createTempFileOfSize(1000, true);
        $file = File::new($tempFile);

        $this->assertTrue($file->cantBeSampled(10, 4096));
    }

    /**
     * Test that cantBeSampled() returns false for large files.
     */
    public function testCantBeSampledReturnsFalseForLargeFiles(): void
    {
        // A file large enough to be sampled
        $tempFile = $this->createTempFileOfSize(1024 * 1024, true); // 1MB
        $file = File::new($tempFile);

        $this->assertFalse($file->cantBeSampled(10, 4096));
    }

    /**
     * Test that isLikelyBinary() returns true for files with control characters.
     * The library detects binary by looking for control characters (below 0x20)
     * other than tab, newline, and carriage return.
     */
    public function testIsLikelyBinaryReturnsTrueForFilesWithControlChars(): void
    {
        // Create a file with control characters which should be detected as binary
        // We need the control chars to be in the first 2 or last 2 blocks (edge blocks)
        $content = "\x01\x02\x03\x04" . random_bytes(20000); // Control chars at start
        $tempFile = $this->createTempFileWithContents($content);
        $file = File::new($tempFile);

        $this->assertTrue($file->isLikelyBinary());
    }

    /**
     * Test that isLikelyBinary() returns false for text files.
     */
    public function testIsLikelyBinaryReturnsFalseForTextFiles(): void
    {
        $tempFile = $this->createTempFileOfSize(10000, false);
        $file = File::new($tempFile);

        $this->assertFalse($file->isLikelyBinary());
    }

    /**
     * Test that readMeaningfulBytesAtOffset() returns correct number of bytes.
     */
    public function testReadMeaningfulBytesAtOffsetReturnsCorrectLength(): void
    {
        $tempFile = $this->createTempFileOfSize(10000, true);
        $file = File::new($tempFile);

        $data = $file->readMeaningfulBytesAtOffset(0, 4096);

        $this->assertSame(4096, strlen($data));
    }

    /**
     * Test that readMeaningfulBytesAtOffset() with negative offset throws exception.
     */
    public function testReadMeaningfulBytesAtOffsetWithNegativeOffsetThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid offset or length');

        $tempFile = $this->createTempFileOfSize(10000, true);
        $file = File::new($tempFile);
        $file->readMeaningfulBytesAtOffset(-1, 100);
    }

    /**
     * Test that readMeaningfulBytesAtOffset() with zero length throws exception.
     */
    public function testReadMeaningfulBytesAtOffsetWithZeroLengthThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid offset or length');

        $tempFile = $this->createTempFileOfSize(10000, true);
        $file = File::new($tempFile);
        $file->readMeaningfulBytesAtOffset(0, 0);
    }

    /**
     * Test that readMeaningfulBytesAtOffset() with range exceeding file size throws exception.
     */
    public function testReadMeaningfulBytesAtOffsetExceedingFileSizeThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Read range exceeds file size');

        $tempFile = $this->createTempFileOfSize(1000, true);
        $file = File::new($tempFile);
        $file->readMeaningfulBytesAtOffset(900, 200); // 900 + 200 = 1100 > 1000
    }

    /**
     * Test that reading from the same offset twice returns cached data.
     */
    public function testReadMeaningfulBytesAtOffsetUsesCache(): void
    {
        $tempFile = $this->createTempFileOfSize(10000, true);
        $file = File::new($tempFile);

        $data1 = $file->readMeaningfulBytesAtOffset(0, 4096);
        $data2 = $file->readMeaningfulBytesAtOffset(0, 4096);

        // Same offset and length should return cached result
        $this->assertSame($data1, $data2);
    }

    /**
     * Test that reading with same offset but different length returns different data.
     * This verifies the cache key includes the length parameter (bug fix).
     */
    public function testCacheKeyIncludesLength(): void
    {
        // Create a file with predictable content
        $content = str_repeat('ABCD', 2500); // 10000 bytes
        $tempFile = $this->createTempFileWithContents($content);
        $file = File::new($tempFile);

        // Read 100 bytes at offset 0
        $data100 = $file->readMeaningfulBytesAtOffset(0, 100);

        // Read 200 bytes at offset 0
        $data200 = $file->readMeaningfulBytesAtOffset(0, 200);

        // They should have different lengths (cache key should be offset:length)
        $this->assertSame(100, strlen($data100));
        $this->assertSame(200, strlen($data200));
        $this->assertNotSame($data100, $data200);
    }
}
