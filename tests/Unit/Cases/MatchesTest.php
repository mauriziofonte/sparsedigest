<?php

declare(strict_types=1);

namespace Mfonte\SparseDigest\Test\Unit\Cases;

use Mfonte\SparseDigest\Exception\InvalidFileException;
use Mfonte\SparseDigest\File;
use Mfonte\SparseDigest\FileHasher;
use Mfonte\SparseDigest\SparseHasher;
use Mfonte\SparseDigest\Test\Unit\TestCase;

/**
 * SparseHasher::matches() and FileHasher::matches().
 */
final class MatchesTest extends TestCase
{
    public function testIdenticalFilesMatch(): void
    {
        $contents = random_bytes(1024 * 1024);
        $a = $this->createTempFileWithContents($contents);
        $b = $this->createTempFileWithContents($contents);

        $this->assertTrue(SparseHasher::new($a)->matches($b));
        $this->assertTrue(SparseHasher::new($a)->matches(new \SplFileInfo($b), 20, 8192));
        $this->assertTrue(SparseHasher::new($a)->matches(File::new($b)));
        $this->assertTrue(FileHasher::new($a)->matches($b));
    }

    public function testDifferentSizesDoNotMatch(): void
    {
        $contents = random_bytes(1024 * 1024);
        $a = $this->createTempFileWithContents($contents);
        $b = $this->createTempFileWithContents($contents . 'x');

        $this->assertFalse(SparseHasher::new($a)->matches($b));
        $this->assertFalse(FileHasher::new($a)->matches($b));
    }

    public function testChangeInSampledBlockIsDetected(): void
    {
        $contents = random_bytes(1024 * 1024);
        $a = $this->createTempFileWithContents($contents);
        $changed = $contents;
        $changed[0] = $changed[0] === 'A' ? 'B' : 'A';
        $b = $this->createTempFileWithContents($changed);

        $this->assertFalse(SparseHasher::new($a)->matches($b));
        $this->assertFalse(FileHasher::new($a)->matches($b));
    }

    public function testFileHasherDetectsChangeAnywhere(): void
    {
        $contents = random_bytes(1024 * 1024);
        $a = $this->createTempFileWithContents($contents);
        // Middle byte: generally outside the sampled blocks.
        $changed = $contents;
        $middle = (int) (strlen($changed) / 2) + 7;
        $changed[$middle] = $changed[$middle] === 'A' ? 'B' : 'A';
        $b = $this->createTempFileWithContents($changed);

        $this->assertFalse(FileHasher::new($a)->matches($b));
    }

    public function testMatchesAcceptsStreams(): void
    {
        $contents = random_bytes(500000);
        $a = $this->createTempFileWithContents($contents);
        $handle = fopen('php://memory', 'w+b');
        if ($handle === false) {
            throw new \RuntimeException('Unable to open php://memory');
        }
        fwrite($handle, $contents);

        $this->assertTrue(SparseHasher::new($a)->matches($handle));
        $this->assertTrue(FileHasher::new($a)->matches($handle));
        fclose($handle);
    }

    public function testInvalidOtherFileThrows(): void
    {
        $this->expectException(InvalidFileException::class);

        SparseHasher::new($this->createTempFileOfSize(1000))->matches('/path/to/nonexistent/file.bin');
    }
}
