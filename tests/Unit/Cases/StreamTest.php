<?php

declare(strict_types=1);

namespace Mfonte\SparseDigest\Test\Unit\Cases;

use Mfonte\SparseDigest\Exception\InvalidFileException;
use Mfonte\SparseDigest\Exception\IOException;
use Mfonte\SparseDigest\File;
use Mfonte\SparseDigest\FileHasher;
use Mfonte\SparseDigest\SparseHasher;
use Mfonte\SparseDigest\Test\Unit\TestCase;

/**
 * Hashing of caller-provided streams.
 */
final class StreamTest extends TestCase
{
    /**
     * @return resource
     */
    private function openRead(string $path)
    {
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new \RuntimeException("Unable to open {$path}");
        }

        return $handle;
    }

    /**
     * @return resource
     */
    private function memoryStream(string $contents)
    {
        $handle = fopen('php://memory', 'w+b');
        if ($handle === false) {
            throw new \RuntimeException('Unable to open php://memory');
        }
        fwrite($handle, $contents);

        return $handle;
    }

    public function testStreamOfBinaryFileHashesLikeThePath(): void
    {
        $path = $this->createTempFileOfSize(1024 * 1024);
        $handle = $this->openRead($path);

        $fromStream = SparseHasher::new($handle)->hash();
        $fromPath = SparseHasher::new($path)->hash();

        $this->assertTrue($fromStream->isSparse());
        $this->assertTrue($fromStream->equals($fromPath));
        fclose($handle);
    }

    public function testStreamOfTextFileFallsBackToFullHash(): void
    {
        $contents = str_repeat('plain text line' . "\n", 500);
        $handle = $this->memoryStream($contents);

        $hash = SparseHasher::new($handle)->hashAs('sha256');

        $this->assertFalse($hash->isSparse());
        $this->assertSame(hash('sha256', $contents), $hash->toHex());
        fclose($handle);
    }

    public function testFullHashOfStreamMatchesHashOfContents(): void
    {
        $contents = random_bytes(200000);
        $handle = $this->memoryStream($contents);
        // Move the pointer: hashing must still start from offset 0.
        fseek($handle, 1234);

        $hash = FileHasher::new($handle)->hashAs('md5');

        $this->assertSame(md5($contents), $hash->toHex());
        fclose($handle);
    }

    public function testEmptyStreamIsSupported(): void
    {
        $handle = $this->memoryStream('');

        $this->assertSame(hash('sha256', ''), SparseHasher::new($handle)->hashAs('sha256')->toHex());
        fclose($handle);
    }

    public function testStreamIsNotClosedByTheLibrary(): void
    {
        $handle = $this->memoryStream(random_bytes(1000));

        $file = File::new($handle);
        $this->assertTrue($file->isStream());
        $file->hashWith('sha1');
        unset($file);

        $this->assertTrue(is_resource($handle));
        fclose($handle);
    }

    public function testStreamClosedByCallerThrowsIOException(): void
    {
        $handle = $this->memoryStream(random_bytes(1000));
        $file = File::new($handle);
        fclose($handle);

        $this->expectException(IOException::class);
        $file->readBytesAtOffset(0, 10);
    }

    public function testNonSeekableStreamIsRejected(): void
    {
        $handle = fopen('php://output', 'wb');
        if ($handle === false) {
            $this->markTestSkipped('php://output is not available');
        }

        try {
            $this->expectException(InvalidFileException::class);
            File::new($handle);
        } finally {
            fclose($handle);
        }
    }

    public function testWriteOnlyStreamIsRejected(): void
    {
        $path = $this->createTempFileOfSize(1000);
        $handle = fopen($path, 'ab');
        if ($handle === false) {
            throw new \RuntimeException("Unable to open {$path}");
        }

        try {
            $this->expectException(InvalidFileException::class);
            $this->expectExceptionMessage('not readable');
            File::new($handle);
        } finally {
            fclose($handle);
        }
    }
}
