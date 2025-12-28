<?php
declare(strict_types=1);

namespace Mfonte\SparseDigest\Benchmarks;

use Mfonte\SparseDigest\SparseHasher;
use Mfonte\SparseDigest\File;

/**
 * Benchmark: SparseDigest vs hash_file()
 *
 * This is the most important benchmark. It measures the core value proposition
 * of this library: how much faster is sparse hashing compared to full file hashing?
 *
 * @Groups({"core", "performance"})
 * @BeforeMethods({"setUp"})
 * @Revs(10)
 * @Iterations(5)
 * @Warmup(1)
 */
class SparseVsFullHashBench extends AbstractBenchmark
{
    /** @var array<string, string> */
    private $files = [];

    /**
     * Set up test files of various sizes.
     *
     * @return void
     */
    public function setUp(): void
    {
        // Create files of various sizes for benchmarking
        $sizes = [
            '10KB' => 10 * 1024,
            '100KB' => 100 * 1024,
            '1MB' => 1024 * 1024,
            '10MB' => 10 * 1024 * 1024,
            '50MB' => 50 * 1024 * 1024,
            '100MB' => 100 * 1024 * 1024,
        ];

        foreach ($sizes as $label => $size) {
            $this->files[$label] = $this->createTempFile($size, "sparse_vs_full_{$label}");
        }
    }

    /**
     * Provides file sizes for parameterized benchmarks.
     *
     * @return \Generator
     */
    public function provideFileSizes(): \Generator
    {
        yield '10KB' => ['size' => '10KB'];
        yield '100KB' => ['size' => '100KB'];
        yield '1MB' => ['size' => '1MB'];
        yield '10MB' => ['size' => '10MB'];
        yield '50MB' => ['size' => '50MB'];
        yield '100MB' => ['size' => '100MB'];
    }

    // =========================================================================
    // SPARSE DIGEST BENCHMARKS
    // =========================================================================

    /**
     * Benchmark SparseDigest with default settings (10% coverage, 4096 block size).
     *
     * @ParamProviders({"provideFileSizes"})
     * @Subject
     *
     * @param array<string, mixed> $params
     *
     * @return void
     */
    public function benchSparseDigestDefault(array $params): void
    {
        $file = $this->files[$params['size']];
        $sd = SparseHasher::new($file);
        $sd->hash(10, 4096);
    }

    /**
     * Benchmark SparseDigest with 5% coverage (faster, less accurate).
     *
     * @ParamProviders({"provideFileSizes"})
     * @Subject
     *
     * @param array<string, mixed> $params
     *
     * @return void
     */
    public function benchSparseDigest5Percent(array $params): void
    {
        $file = $this->files[$params['size']];
        $sd = SparseHasher::new($file);
        $sd->hash(5, 4096);
    }

    /**
     * Benchmark SparseDigest with 20% coverage (slower, more accurate).
     *
     * @ParamProviders({"provideFileSizes"})
     * @Subject
     *
     * @param array<string, mixed> $params
     *
     * @return void
     */
    public function benchSparseDigest20Percent(array $params): void
    {
        $file = $this->files[$params['size']];
        $sd = SparseHasher::new($file);
        $sd->hash(20, 4096);
    }

    // =========================================================================
    // FULL HASH BENCHMARKS (BASELINE)
    // =========================================================================

    /**
     * Benchmark native hash_file() with xxh3 (fastest algorithm).
     *
     * @ParamProviders({"provideFileSizes"})
     * @Subject
     *
     * @param array<string, mixed> $params
     *
     * @return void
     */
    public function benchHashFileXxh3(array $params): void
    {
        $file = $this->files[$params['size']];
        hash_file('xxh3', $file);
    }

    /**
     * Benchmark native hash_file() with sha256.
     *
     * @ParamProviders({"provideFileSizes"})
     * @Subject
     *
     * @param array<string, mixed> $params
     *
     * @return void
     */
    public function benchHashFileSha256(array $params): void
    {
        $file = $this->files[$params['size']];
        hash_file('sha256', $file);
    }

    /**
     * Benchmark File::hashWith() - full file hash via library.
     *
     * @ParamProviders({"provideFileSizes"})
     * @Subject
     *
     * @param array<string, mixed> $params
     *
     * @return void
     */
    public function benchFileFullHash(array $params): void
    {
        $file = $this->files[$params['size']];
        $f = File::new($file);
        $f->hashWith('xxh3');
    }
}
