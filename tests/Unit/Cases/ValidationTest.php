<?php

declare(strict_types=1);

namespace Mfonte\SparseDigest\Test\Unit\Cases;

use InvalidArgumentException;
use Mfonte\SparseDigest\Exception\BadMethodCallException;
use Mfonte\SparseDigest\Exception\InvalidFileException;
use Mfonte\SparseDigest\Exception\InvalidRangeException;
use Mfonte\SparseDigest\Exception\InvalidSamplingParameterException;
use Mfonte\SparseDigest\Exception\IOException;
use Mfonte\SparseDigest\Exception\SparseDigestException;
use Mfonte\SparseDigest\Exception\UnsupportedAlgorithmException;
use Mfonte\SparseDigest\File;
use Mfonte\SparseDigest\FileHasher;
use Mfonte\SparseDigest\HashResult;
use Mfonte\SparseDigest\SparseHasher;
use Mfonte\SparseDigest\Strategy\SparseStrategy;
use Mfonte\SparseDigest\Test\Unit\TestCase;
use SplFileInfo;

/**
 * Input validation, compute time reporting and HashResult comparison.
 */
final class ValidationTest extends TestCase
{
    /**
     * @return array<string, array{int}>
     */
    public function nonPositiveSampleSizeProvider(): array
    {
        return [
            'zero' => [0],
            'negative multiple of 64' => [-64],
            'negative' => [-4096],
        ];
    }

    /**
     * @dataProvider nonPositiveSampleSizeProvider
     */
    public function testNonPositiveSampleSizeThrowsInvalidArgument(int $sampleSize): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Sample size must be a positive integer divisible by 64');

        SparseHasher::new($this->createTempFileOfSize(100000))->hash(10, $sampleSize);
    }

    public function testInvalidAlgorithmIsRejectedOnSparsePath(): void
    {
        // Large binary file: would be sparse-hashed.
        $hasher = SparseHasher::new($this->createTempFileOfSize(1024 * 1024));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid hash algorithm: nope');
        $hasher->hashAs('nope');
    }

    public function testInvalidAlgorithmIsRejectedOnFullFallbackPath(): void
    {
        // Text file: would fall back to a full-file hash.
        $hasher = SparseHasher::new($this->createTempFileWithContents(str_repeat('plain text ', 100)));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid hash algorithm: nope');
        $hasher->hashAs('nope');
    }

    public function testFileHasherRejectsInvalidAlgorithm(): void
    {
        $hasher = FileHasher::new($this->createTempFileOfSize(1000));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid hash algorithm: nope');
        $hasher->hashAs('nope');
    }

    public function testComputeTimeIsZeroBeforeHashing(): void
    {
        $hasher = SparseHasher::new($this->createTempFileOfSize(1024 * 1024));

        $this->assertSame(0.0, $hasher->getComputeTime());
    }

    public function testComputeTimeIsMeasuredForSparseHash(): void
    {
        $hasher = SparseHasher::new($this->createTempFileOfSize(1024 * 1024));
        $hasher->hash();

        $this->assertGreaterThan(0.0, $hasher->getComputeTime());
    }

    public function testComputeTimeIsMeasuredForFullFallback(): void
    {
        $hasher = SparseHasher::new($this->createTempFileWithContents(str_repeat('plain text ', 100)));
        $hasher->hash();

        $this->assertGreaterThan(0.0, $hasher->getComputeTime());
    }

    public function testNonExistentSplFileInfoThrowsInvalidArgument(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('does not exist');

        SparseHasher::new(new SplFileInfo('/path/to/nonexistent/file.bin'));
    }

    public function testUnsupportedAlgorithmExceptionExposesAlgorithm(): void
    {
        try {
            FileHasher::new($this->createTempFileOfSize(1000))->hashAs('nope');
            $this->fail('Expected UnsupportedAlgorithmException');
        } catch (UnsupportedAlgorithmException $e) {
            $this->assertSame('nope', $e->getAlgorithm());
        }
    }

    /**
     * @return array<string, array{callable(): mixed, class-string<\Throwable>}>
     */
    public function exceptionHierarchyProvider(): array
    {
        return [
            'missing file' => [static function () {
                return SparseHasher::new('/path/to/nonexistent/file.bin');
            }, InvalidFileException::class],
            'invalid input type' => [static function () {
                /** @phpstan-ignore-next-line Testing invalid type intentionally */
                return File::new(123);
            }, InvalidFileException::class],
            'bad coverage' => [function () {
                return SparseHasher::new($this->createTempFileOfSize(1000))->hash(0);
            }, InvalidSamplingParameterException::class],
            'bad sample size' => [function () {
                return SparseHasher::new($this->createTempFileOfSize(1000))->hash(10, 100);
            }, InvalidSamplingParameterException::class],
            'bad range' => [function () {
                return File::new($this->createTempFileOfSize(1000))->readBytesAtOffset(900, 200);
            }, InvalidRangeException::class],
            'clone' => [function () {
                return clone File::new($this->createTempFileOfSize(1000));
            }, BadMethodCallException::class],
            'bad hex' => [static function () {
                return HashResult::fromHex('xyz');
            }, InvalidArgumentException::class],
        ];
    }

    /**
     * @dataProvider exceptionHierarchyProvider
     *
     * @param callable(): mixed $callback
     * @param class-string<\Throwable> $expected
     */
    public function testExceptionsImplementLibraryInterface(callable $callback, string $expected): void
    {
        try {
            $callback();
            $this->fail("Expected {$expected}");
        } catch (SparseDigestException $e) {
            $this->assertInstanceOf($expected, $e);
        }
    }

    public function testLibraryExceptionsRemainCatchableAsSplExceptions(): void
    {
        $this->assertInstanceOf(\InvalidArgumentException::class, new InvalidFileException());
        $this->assertInstanceOf(\InvalidArgumentException::class, UnsupportedAlgorithmException::forAlgorithm('x'));
        $this->assertInstanceOf(\InvalidArgumentException::class, new InvalidSamplingParameterException());
        $this->assertInstanceOf(\InvalidArgumentException::class, new InvalidRangeException());
        $this->assertInstanceOf(\RuntimeException::class, new IOException());
        $this->assertInstanceOf(\BadMethodCallException::class, new BadMethodCallException());
    }

    public function testSparseHashRecordsAlgorithmAndMode(): void
    {
        $hasher = SparseHasher::new($this->createTempFileOfSize(1024 * 1024));

        $sparse = $hasher->hashAs('sha256');
        $this->assertTrue($sparse->isSparse());
        $this->assertSame('sha256', $sparse->getAlgorithm());

        $default = $hasher->hash();
        $this->assertSame((new SparseStrategy())->getDefaultAlgorithm(), $default->getAlgorithm());
    }

    public function testFullFallbackRecordsAlgorithmAndMode(): void
    {
        $hash = SparseHasher::new($this->createTempFileWithContents(str_repeat('plain text ', 100)))->hashAs('md5');

        $this->assertFalse($hash->isSparse());
        $this->assertSame('md5', $hash->getAlgorithm());
    }

    public function testFileHasherRecordsAlgorithm(): void
    {
        $hash = FileHasher::new($this->createTempFileOfSize(1000))->hashAs('sha1');

        $this->assertFalse($hash->isSparse());
        $this->assertSame('sha1', $hash->getAlgorithm());
    }

    public function testHashResultRoundTripsThroughHexAndBinary(): void
    {
        $original = SparseHasher::new($this->createTempFileOfSize(1024 * 1024))->hashAs('sha256');

        $fromHex = HashResult::fromHex(strtoupper($original->toHex()), 'sha256');
        $fromBinary = HashResult::fromBinary($original->toBinary());

        $this->assertTrue($fromHex->equals($original));
        $this->assertTrue($fromBinary->equals($original));
        $this->assertSame('sha256', $fromHex->getAlgorithm());
        $this->assertNull($fromBinary->getAlgorithm());
        $this->assertSame(base64_encode($original->toBinary()), $original->toBase64());
    }

    /**
     * @return array<string, array{string}>
     */
    public function invalidHexProvider(): array
    {
        return [
            'empty' => [''],
            'odd length' => ['abc'],
            'non hex' => ['zz'],
        ];
    }

    /**
     * @dataProvider invalidHexProvider
     */
    public function testFromHexRejectsInvalidInput(string $hex): void
    {
        $this->expectException(InvalidArgumentException::class);

        HashResult::fromHex($hex);
    }

    public function testPreferredAlgorithmIsUsedByDefault(): void
    {
        $strategy = new SparseStrategy();
        $available = hash_algos();
        $expected = null;
        foreach (SparseStrategy::PREFERRED_ALGORITHMS as $algo) {
            if (in_array($algo, $available, true)) {
                $expected = $algo;

                break;
            }
        }

        $this->assertSame($expected, $strategy->getDefaultAlgorithm());
    }

    public function testHashResultEqualsAnotherHashResult(): void
    {
        $a = HashResult::new("\x01\x02\xab");
        $b = HashResult::new("\x01\x02\xab");
        $c = HashResult::new("\x01\x02\xac");

        $this->assertTrue($a->equals($b));
        $this->assertFalse($a->equals($c));
    }

    public function testHashResultEqualsHexStringCaseInsensitively(): void
    {
        $hash = HashResult::new("\x01\x02\xab");

        $this->assertTrue($hash->equals('0102ab'));
        $this->assertTrue($hash->equals('0102AB'));
        $this->assertFalse($hash->equals('0102ac'));
        $this->assertFalse($hash->equals(''));
    }

    public function testSameFileProducesEqualResults(): void
    {
        $file = $this->createTempFileOfSize(1024 * 1024);

        $this->assertTrue(SparseHasher::new($file)->hash()->equals(SparseHasher::new($file)->hash()));
    }
}
