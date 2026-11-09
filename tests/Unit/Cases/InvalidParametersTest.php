<?php

declare(strict_types=1);

namespace Mfonte\SparseDigest\Test\Unit\Cases;

use InvalidArgumentException;
use Mfonte\SparseDigest\SparseHasher;
use Mfonte\SparseDigest\Test\Unit\TestCase;

final class InvalidParametersTest extends TestCase
{
    public function testCoverageBelowRangeThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        SparseHasher::new($this->createTempFileOfSize(1024))->hash(0, 4096);
    }

    public function testCoverageAboveRangeThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        SparseHasher::new($this->createTempFileOfSize(1024))->hash(101, 4096);
    }

    public function testInvalidSampleSizeThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        SparseHasher::new($this->createTempFileOfSize(1024))->hash(10, 4097);
    }

    public function testInvalidFileTypeThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        /** @phpstan-ignore-next-line Testing invalid type intentionally */
        SparseHasher::new(12345);
    }

    public function testNonExistentFileThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        SparseHasher::new('/path/to/nonexistent/file.txt');
    }

    public function testNotAFileThrowsException(): void
    {
        // Create a temporary directory.
        $tempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . uniqid('sparsedigest_test_dir_');
        mkdir($tempDir);

        try {
            $this->expectException(InvalidArgumentException::class);
            SparseHasher::new($tempDir);
        } finally {
            rmdir($tempDir);
        }
    }

    public function testEmptyFileIsAccepted(): void
    {
        $hash = SparseHasher::new($this->createTempFileOfSize(0))->hash();

        $this->assertFalse($hash->isSparse());
    }
}
