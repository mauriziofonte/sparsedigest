<?php

declare(strict_types=1);

namespace Mfonte\SparseDigest\Benchmarks;

use Mfonte\SparseDigest\SparseHasher;

/**
 * Benchmark: Memory Usage
 *
 * This benchmark focuses on memory efficiency.
 * SparseDigest should use constant memory regardless of file size,
 * while naive approaches might load more data.
 *
 * @Groups({"memory", "efficiency"})
 * @BeforeMethods({"setUp"})
 * @Revs(3)
 * @Iterations(3)
 * @Warmup(1)
 */
class MemoryBench extends AbstractBenchmark
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
        $sizes = [
            '1MB' => 1024 * 1024,
            '10MB' => 10 * 1024 * 1024,
            '50MB' => 50 * 1024 * 1024,
            '100MB' => 100 * 1024 * 1024,
            '250MB' => 250 * 1024 * 1024,
        ];

        foreach ($sizes as $label => $size) {
            $this->files[$label] = $this->createTempFile($size, "memory_{$label}");
        }
    }

    /**
     * Provides file sizes for parameterized benchmarks.
     *
     * @return \Generator
     */
    public function provideFileSizes(): \Generator
    {
        yield '1MB' => ['size' => '1MB'];
        yield '10MB' => ['size' => '10MB'];
        yield '50MB' => ['size' => '50MB'];
        yield '100MB' => ['size' => '100MB'];
        yield '250MB' => ['size' => '250MB'];
    }

    /**
     * Benchmark SparseDigest memory usage with large files.
     *
     * PHPBench will track peak memory usage.
     *
     * @ParamProviders({"provideFileSizes"})
     * @Subject
     *
     * @param array<string, mixed> $params
     *
     * @return void
     */
    public function benchSparseMemory(array $params): void
    {
        $file = $this->files[$params['size']];
        $sd = SparseHasher::new($file);
        $sd->hash(10, 4096);
    }

    /**
     * Benchmark hash_file memory usage (should be efficient).
     *
     * @ParamProviders({"provideFileSizes"})
     * @Subject
     *
     * @param array<string, mixed> $params
     *
     * @return void
     */
    public function benchNativeMemory(array $params): void
    {
        $file = $this->files[$params['size']];
        hash_file('xxh3', $file);
    }

    /**
     * Benchmark file_get_contents + hash memory usage (worst case baseline).
     * This shows what NOT to do - loads entire file into memory.
     *
     * Only test with smaller files to avoid OOM.
     *
     * @Subject
     *
     * @return void
     */
    public function benchNaiveFullLoadMemory(): void
    {
        $file = $this->files['10MB']; // Limit to 10MB to avoid OOM
        $content = file_get_contents($file);
        hash('xxh3', $content);
        unset($content);
    }
}
