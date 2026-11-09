<?php

declare(strict_types=1);

namespace Mfonte\SparseDigest\Test\Unit\Cases;

use InvalidArgumentException;
use Mfonte\SparseDigest\File;
use Mfonte\SparseDigest\SparseHasher;
use Mfonte\SparseDigest\Test\Unit\TestCase;
use RuntimeException;

/**
 * Tests for exception handling in the SparseDigest library.
 *
 * These tests verify that appropriate exceptions are thrown
 * for invalid inputs and error conditions.
 */
class ExceptionsTest extends TestCase
{
    // ========================================
    // SparseHasher Exception Tests
    // ========================================

    /**
     * Test that SparseHasher::new throws exception for non-existent file path.
     */
    public function testSparseDigestThrowsForNonExistentFile(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('does not exist');

        SparseHasher::new('/this/file/does/not/exist.bin');
    }

    /**
     * Test that SparseHasher::new throws exception for directory path.
     */
    public function testSparseDigestThrowsForDirectory(): void
    {
        $this->expectException(InvalidArgumentException::class);

        SparseHasher::new(sys_get_temp_dir());
    }

    /**
     * Test that SparseHasher::new throws exception for null input.
     */
    public function testSparseDigestThrowsForNullInput(): void
    {
        // PHP 8+ throws InvalidArgumentException from our code, not TypeError
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid file type');

        /** @phpstan-ignore-next-line Testing invalid type intentionally */
        SparseHasher::new(null);
    }

    /**
     * Test that SparseHasher::new throws exception for array input.
     */
    public function testSparseDigestThrowsForArrayInput(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid file type');

        /** @phpstan-ignore-next-line Testing invalid type intentionally */
        SparseHasher::new(['not', 'a', 'file']);
    }

    /**
     * Test that SparseHasher::new throws exception for integer input.
     */
    public function testSparseDigestThrowsForIntegerInput(): void
    {
        $this->expectException(InvalidArgumentException::class);

        /** @phpstan-ignore-next-line Testing invalid type intentionally */
        SparseHasher::new(12345);
    }

    /**
     * Test that SparseHasher::new throws exception for object input.
     */
    public function testSparseDigestThrowsForInvalidObjectInput(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid file type');

        /** @phpstan-ignore-next-line Testing invalid type intentionally */
        SparseHasher::new(new \stdClass());
    }

    /**
     * Test that hashAs throws exception for invalid algorithm.
     */
    public function testHashAsThrowsForInvalidAlgorithm(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid hash algorithm');

        $tempFile = $this->createTempFileOfSize(10000, true);
        $sd = SparseHasher::new($tempFile);
        $sd->hashAs('this_is_not_a_valid_algorithm');
    }

    /**
     * Test that hashAs throws exception for empty algorithm string.
     */
    public function testHashAsThrowsForEmptyAlgorithm(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid hash algorithm');

        $tempFile = $this->createTempFileOfSize(10000, true);
        $sd = SparseHasher::new($tempFile);
        $sd->hashAs('');
    }

    // ========================================
    // File Exception Tests
    // ========================================

    /**
     * Test that File::new throws exception for non-existent file.
     */
    public function testDigestableThrowsForNonExistentFile(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('does not exist');

        File::new('/path/to/nonexistent/file.txt');
    }

    /**
     * Test that File::new throws exception for directory.
     */
    public function testDigestableThrowsForDirectory(): void
    {
        $this->expectException(InvalidArgumentException::class);

        File::new(sys_get_temp_dir());
    }

    /**
     * Test that File::new throws exception for invalid type.
     */
    public function testDigestableThrowsForInvalidType(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid file type');

        /** @phpstan-ignore-next-line Testing invalid type intentionally */
        File::new(42);
    }

    /**
     * Test that File::hashWith throws exception for invalid algorithm.
     */
    public function testDigestableHashWithThrowsForInvalidAlgorithm(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid hash algorithm');

        $tempFile = $this->createTempFileOfSize(1000, true);
        $file = File::new($tempFile);
        $file->hashWith('not_an_algorithm');
    }

    /**
     * Test that readMeaningfulBytesAtOffset throws for negative offset.
     */
    public function testReadMeaningfulBytesThrowsForNegativeOffset(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid offset or length');

        $tempFile = $this->createTempFileOfSize(10000, true);
        $file = File::new($tempFile);
        $file->readMeaningfulBytesAtOffset(-1, 100);
    }

    /**
     * Test that readMeaningfulBytesAtOffset throws for zero length.
     */
    public function testReadMeaningfulBytesThrowsForZeroLength(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid offset or length');

        $tempFile = $this->createTempFileOfSize(10000, true);
        $file = File::new($tempFile);
        $file->readMeaningfulBytesAtOffset(0, 0);
    }

    /**
     * Test that readMeaningfulBytesAtOffset throws for negative length.
     */
    public function testReadMeaningfulBytesThrowsForNegativeLength(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid offset or length');

        $tempFile = $this->createTempFileOfSize(10000, true);
        $file = File::new($tempFile);
        $file->readMeaningfulBytesAtOffset(0, -100);
    }

    /**
     * Test that readMeaningfulBytesAtOffset throws when range exceeds file size.
     */
    public function testReadMeaningfulBytesThrowsWhenExceedingFileSize(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Read range exceeds file size');

        $tempFile = $this->createTempFileOfSize(1000, true);
        $file = File::new($tempFile);
        $file->readMeaningfulBytesAtOffset(900, 200); // 900 + 200 = 1100 > 1000
    }

    /**
     * Test that readMeaningfulBytesAtOffset throws when offset equals file size.
     */
    public function testReadMeaningfulBytesThrowsWhenOffsetEqualsFileSize(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Read range exceeds file size');

        $tempFile = $this->createTempFileOfSize(1000, true);
        $file = File::new($tempFile);
        $file->readMeaningfulBytesAtOffset(1000, 1);
    }

    /**
     * Test that readMeaningfulBytesAtOffset throws when offset exceeds file size.
     */
    public function testReadMeaningfulBytesThrowsWhenOffsetExceedsFileSize(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Read range exceeds file size');

        $tempFile = $this->createTempFileOfSize(1000, true);
        $file = File::new($tempFile);
        $file->readMeaningfulBytesAtOffset(2000, 100);
    }

    // ========================================
    // File Permission Tests (if possible)
    // ========================================

    /**
     * Test behavior with unreadable file (if we can create one).
     */
    public function testUnreadableFile(): void
    {
        // Skip on Windows (no POSIX permissions) or when running as root (root can read anything)
        if (DIRECTORY_SEPARATOR === '\\' || (function_exists('posix_getuid') && posix_getuid() === 0)) {
            $this->markTestSkipped('Cannot test file permissions on Windows or when running as root');
        }

        $tempFile = $this->createTempFileOfSize(1000, true);

        // Make file unreadable
        chmod($tempFile, 0000);

        try {
            // The library throws InvalidArgumentException for unreadable files
            $this->expectException(InvalidArgumentException::class);
            $this->expectExceptionMessage('not readable');
            File::new($tempFile);
        } finally {
            // Restore permissions for cleanup
            chmod($tempFile, 0644);
        }
    }

    // ========================================
    // Edge Case Exception Tests
    // ========================================

    /**
     * Test that empty string path throws exception.
     */
    public function testEmptyStringPathThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);

        File::new('');
    }

    /**
     * Test that whitespace-only path throws exception.
     */
    public function testWhitespacePathThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);

        File::new('   ');
    }

    /**
     * Test special characters in file path that doesn't exist.
     */
    public function testSpecialCharactersInNonExistentPath(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('does not exist');

        File::new('/path/with/special/chars/!@#$%^&*().txt');
    }

    /**
     * Test that deleted file during operation causes proper error handling.
     */
    public function testFileDeletedDuringOperation(): void
    {
        $tempFile = $this->createTempFileOfSize(100000, true);
        $file = File::new($tempFile);

        // Get size to ensure file is opened
        $file->getSize();

        // Delete the file
        unlink($tempFile);

        // Remove from cleanup list since we manually deleted
        $this->tempFiles = array_filter($this->tempFiles, function ($f) use ($tempFile) {
            return $f !== $tempFile;
        });

        // Now try to read - should handle gracefully
        // The behavior depends on whether the file handle is still valid
        // This test documents the actual behavior
        try {
            // File handle might still be valid on some systems
            $data = $file->readMeaningfulBytesAtOffset(0, 100);
            $this->assertIsString($data);
        } catch (RuntimeException $e) {
            // Expected on systems that can't read deleted file
            $this->assertStringContainsString('Unable', $e->getMessage());
        }
    }
}
