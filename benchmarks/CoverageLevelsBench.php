<?php
declare(strict_types=1);

namespace Mfonte\SparseDigest\Benchmarks;

use Mfonte\SparseDigest\SparseHasher;

/**
 * Benchmark: Coverage Levels Impact
 *
 * This benchmark measures how different coverage percentages affect performance.
 * It helps users understand the tradeoff between speed and hash uniqueness.
 *
 * Lower coverage = faster but less unique hash
 * Higher coverage = slower but more unique hash
 *
 * @Groups({"coverage", "tuning"})
 * @BeforeMethods({"setUp"})
 * @Revs(10)
 * @Iterations(5)
 * @Warmup(1)
 */
class CoverageLevelsBench extends AbstractBenchmark
{
    /** @var string */
    private $file;

    /**
     * Set up a 50MB test file.
     *
     * @return void
     */
    public function setUp(): void
    {
        // 50MB file for meaningful coverage measurements
        $this->file = $this->createTempFile(50 * 1024 * 1024, 'coverage_50mb');
    }

    /**
     * Provides coverage levels for parameterized benchmarks.
     *
     * @return \Generator
     */
    public function provideCoverageLevels(): \Generator
    {
        yield '1%' => ['coverage' => 1];
        yield '2%' => ['coverage' => 2];
        yield '5%' => ['coverage' => 5];
        yield '10%' => ['coverage' => 10];
        yield '15%' => ['coverage' => 15];
        yield '20%' => ['coverage' => 20];
        yield '25%' => ['coverage' => 25];
        yield '30%' => ['coverage' => 30];
        yield '50%' => ['coverage' => 50];
    }

    /**
     * Benchmark sparse hashing with varying coverage levels.
     *
     * @ParamProviders({"provideCoverageLevels"})
     * @Subject
     *
     * @param array<string, mixed> $params
     *
     * @return void
     */
    public function benchCoverageLevel(array $params): void
    {
        $sd = SparseHasher::new($this->file);
        $sd->hash($params['coverage'], 4096);
    }

    /**
     * Benchmark with different sample sizes at fixed 10% coverage.
     *
     * @return \Generator
     */
    public function provideSampleSizes(): \Generator
    {
        yield '512B' => ['sampleSize' => 512];
        yield '1KB' => ['sampleSize' => 1024];
        yield '2KB' => ['sampleSize' => 2048];
        yield '4KB' => ['sampleSize' => 4096];
        yield '8KB' => ['sampleSize' => 8192];
        yield '16KB' => ['sampleSize' => 16384];
        yield '32KB' => ['sampleSize' => 32768];
        yield '64KB' => ['sampleSize' => 65536];
    }

    /**
     * Benchmark sparse hashing with varying sample sizes.
     *
     * @ParamProviders({"provideSampleSizes"})
     * @Subject
     *
     * @param array<string, mixed> $params
     *
     * @return void
     */
    public function benchSampleSize(array $params): void
    {
        $sd = SparseHasher::new($this->file);
        $sd->hash(10, $params['sampleSize']);
    }
}
