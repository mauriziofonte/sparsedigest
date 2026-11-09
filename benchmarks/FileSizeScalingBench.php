<?php

declare(strict_types=1);

namespace Mfonte\SparseDigest\Benchmarks;

use Mfonte\SparseDigest\SparseHasher;

/**
 * Benchmark: File Size Scaling
 *
 * This benchmark shows how performance scales with file size.
 * SparseDigest should show sub-linear scaling (O(1) relative to file size)
 * while hash_file scales linearly (O(n)).
 *
 * This is the key insight: for large files, sparse hashing is much faster.
 *
 * @Groups({"scaling", "core"})
 * @BeforeMethods({"setUp"})
 * @Revs(5)
 * @Iterations(3)
 * @Warmup(1)
 */
class FileSizeScalingBench extends AbstractBenchmark
{
    /** @var array<string, string> */
    private $files = [];

    /**
     * Set up test files with exponentially increasing sizes.
     *
     * @return void
     */
    public function setUp(): void
    {
        $sizes = [
            '1KB' => 1024,
            '10KB' => 10 * 1024,
            '100KB' => 100 * 1024,
            '1MB' => 1024 * 1024,
            '5MB' => 5 * 1024 * 1024,
            '10MB' => 10 * 1024 * 1024,
            '25MB' => 25 * 1024 * 1024,
            '50MB' => 50 * 1024 * 1024,
            '100MB' => 100 * 1024 * 1024,
            '200MB' => 200 * 1024 * 1024,
        ];

        foreach ($sizes as $label => $size) {
            $this->files[$label] = $this->createTempFile($size, "scaling_{$label}");
        }
    }

    /**
     * Provides file sizes for parameterized benchmarks.
     *
     * @return \Generator
     */
    public function provideFileSizes(): \Generator
    {
        yield '1KB' => ['size' => '1KB'];
        yield '10KB' => ['size' => '10KB'];
        yield '100KB' => ['size' => '100KB'];
        yield '1MB' => ['size' => '1MB'];
        yield '5MB' => ['size' => '5MB'];
        yield '10MB' => ['size' => '10MB'];
        yield '25MB' => ['size' => '25MB'];
        yield '50MB' => ['size' => '50MB'];
        yield '100MB' => ['size' => '100MB'];
        yield '200MB' => ['size' => '200MB'];
    }

    /**
     * Benchmark SparseDigest scaling with file size.
     *
     * @ParamProviders({"provideFileSizes"})
     * @Subject
     *
     * @param array<string, mixed> $params
     *
     * @return void
     */
    public function benchSparseScaling(array $params): void
    {
        $file = $this->files[$params['size']];
        $sd = SparseHasher::new($file);
        $sd->hash(10, 4096);
    }

    /**
     * Benchmark hash_file scaling with file size (baseline).
     *
     * @ParamProviders({"provideFileSizes"})
     * @Subject
     *
     * @param array<string, mixed> $params
     *
     * @return void
     */
    public function benchNativeScaling(array $params): void
    {
        $file = $this->files[$params['size']];
        hash_file('xxh3', $file);
    }
}
