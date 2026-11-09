<?php

declare(strict_types=1);

namespace Mfonte\SparseDigest\Benchmarks;

use Mfonte\SparseDigest\SparseHasher;

/**
 * Benchmark: Algorithm Performance
 *
 * This benchmark measures how different hashing algorithms affect performance.
 * Some algorithms (xxh3, crc32) are much faster than cryptographic ones (sha512).
 *
 * @Groups({"algorithms", "tuning"})
 * @BeforeMethods({"setUp"})
 * @Revs(10)
 * @Iterations(5)
 * @Warmup(1)
 */
class AlgorithmBench extends AbstractBenchmark
{
    /** @var string */
    private $file;

    /**
     * Set up a 20MB test file.
     *
     * @return void
     */
    public function setUp(): void
    {
        $this->file = $this->createTempFile(20 * 1024 * 1024, 'algo_20mb');
    }

    /**
     * Provides hashing algorithms for parameterized benchmarks.
     * Ordered roughly by expected performance (fastest first).
     *
     * @return \Generator
     */
    public function provideAlgorithms(): \Generator
    {
        // Fast non-cryptographic hashes
        yield 'xxh3' => ['algo' => 'xxh3'];
        yield 'xxh128' => ['algo' => 'xxh128'];
        yield 'xxh64' => ['algo' => 'xxh64'];
        yield 'xxh32' => ['algo' => 'xxh32'];
        yield 'crc32c' => ['algo' => 'crc32c'];
        yield 'crc32' => ['algo' => 'crc32'];
        yield 'crc32b' => ['algo' => 'crc32b'];
        yield 'adler32' => ['algo' => 'adler32'];
        yield 'murmur3a' => ['algo' => 'murmur3a'];
        yield 'murmur3f' => ['algo' => 'murmur3f'];

        // MD family (medium speed)
        yield 'md4' => ['algo' => 'md4'];
        yield 'md5' => ['algo' => 'md5'];

        // SHA family (slower, cryptographic)
        yield 'sha1' => ['algo' => 'sha1'];
        yield 'sha256' => ['algo' => 'sha256'];
        yield 'sha384' => ['algo' => 'sha384'];
        yield 'sha512' => ['algo' => 'sha512'];

        // SHA-3 family (newer, variable speed)
        yield 'sha3-256' => ['algo' => 'sha3-256'];
        yield 'sha3-512' => ['algo' => 'sha3-512'];
    }

    /**
     * Benchmark sparse hashing with different algorithms.
     *
     * @ParamProviders({"provideAlgorithms"})
     * @Subject
     *
     * @param array<string, mixed> $params
     *
     * @return void
     */
    public function benchAlgorithmSparse(array $params): void
    {
        $sd = SparseHasher::new($this->file);
        $sd->hashAs($params['algo'], 10, 4096);
    }

    /**
     * Benchmark native hash_file with different algorithms (baseline).
     *
     * @ParamProviders({"provideAlgorithms"})
     * @Subject
     *
     * @param array<string, mixed> $params
     *
     * @return void
     */
    public function benchAlgorithmNative(array $params): void
    {
        hash_file($params['algo'], $this->file);
    }
}
