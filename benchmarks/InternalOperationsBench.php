<?php
declare(strict_types=1);

namespace Mfonte\SparseDigest\Benchmarks;

use Mfonte\SparseDigest\File;

/**
 * Benchmark: Internal Operations
 *
 * RATIONALE:
 * This benchmark isolates and measures the overhead of internal operations
 * that compose the SparseDigest algorithm. Understanding these micro-benchmarks
 * helps identify bottlenecks and optimize the library.
 *
 * Key operations measured:
 * - Digestable instantiation overhead (path vs SplFileInfo)
 * - Binary vs text detection cost (affects NULL byte replacement strategy)
 * - Chunk reading patterns (sequential vs random access)
 * - Impact of chunk size on I/O performance
 *
 * WHY THIS MATTERS:
 * The SparseDigest algorithm reads multiple chunks from different file offsets.
 * The readMeaningfulBytesAtOffset() method includes NULL byte replacement logic,
 * which has overhead. These benchmarks help understand:
 * 1. Is file opening a bottleneck? (Creation benchmarks)
 * 2. Is binary detection expensive? (Detection benchmarks)
 * 3. Is sequential or random I/O faster? (Read pattern benchmarks)
 * 4. What's the optimal chunk size? (Chunk size benchmarks)
 *
 * @Groups({"internals", "overhead"})
 * @BeforeMethods({"setUp"})
 * @Revs(20)
 * @Iterations(5)
 * @Warmup(2)
 */
class InternalOperationsBench extends AbstractBenchmark
{
    /** @var string */
    private $binaryFile;

    /** @var string */
    private $textFile;

    /** @var string */
    private $largeFile;

    /**
     * Set up test files.
     *
     * @return void
     */
    public function setUp(): void
    {
        $this->binaryFile = $this->createTempFile(10 * 1024 * 1024, 'internal_binary');
        $this->textFile = $this->createTempTextFile(10 * 1024 * 1024, 'internal_text');
        $this->largeFile = $this->createTempFile(100 * 1024 * 1024, 'internal_large');
    }

    // =========================================================================
    // DIGESTABLE CREATION OVERHEAD
    // =========================================================================

    /**
     * Benchmark: Create a File from a file path.
     *
     * @Subject
     *
     * @return void
     */
    public function benchFileFromPath(): void
    {
        File::new($this->binaryFile);
    }

    /**
     * Benchmark: Create a File from an SplFileInfo.
     *
     * @Subject
     *
     * @return void
     */
    public function benchFileFromSplFileInfo(): void
    {
        $spl = new \SplFileInfo($this->binaryFile);
        File::new($spl);
    }

    // =========================================================================
    // BINARY DETECTION OVERHEAD
    // =========================================================================

    /**
     * Benchmark: Binary detection on a binary file.
     *
     * @Subject
     *
     * @return void
     */
    public function benchBinaryDetectionBinary(): void
    {
        $file = File::new($this->binaryFile);
        $file->isLikelyBinary();
    }

    /**
     * Benchmark: Binary detection on a text file.
     *
     * @Subject
     *
     * @return void
     */
    public function benchBinaryDetectionText(): void
    {
        $file = File::new($this->textFile);
        $file->isLikelyBinary();
    }

    // =========================================================================
    // CHUNK READING OVERHEAD
    // =========================================================================

    /**
     * Provides chunk sizes for benchmarking.
     *
     * @return \Generator
     */
    public function provideChunkSizes(): \Generator
    {
        yield '512B' => ['size' => 512];
        yield '1KB' => ['size' => 1024];
        yield '4KB' => ['size' => 4096];
        yield '16KB' => ['size' => 16384];
        yield '64KB' => ['size' => 65536];
    }

    /**
     * Benchmark: Read chunks from various offsets.
     *
     * @ParamProviders({"provideChunkSizes"})
     * @Subject
     *
     * @param array<string, mixed> $params
     *
     * @return void
     */
    public function benchChunkRead(array $params): void
    {
        $file = File::new($this->largeFile);
        $fileSize = $file->getSize();

        // Read from beginning, middle, and end
        $file->readMeaningfulBytesAtOffset(0, $params['size']);
        $file->readMeaningfulBytesAtOffset((int)($fileSize / 2), $params['size']);
        $file->readMeaningfulBytesAtOffset($fileSize - $params['size'], $params['size']);
    }

    /**
     * Benchmark: Sequential chunk reading.
     *
     * @Subject
     *
     * @return void
     */
    public function benchSequentialRead(): void
    {
        $file = File::new($this->binaryFile);
        $chunkSize = 4096;
        $reads = 100;

        for ($i = 0; $i < $reads; $i++) {
            $file->readMeaningfulBytesAtOffset($i * $chunkSize, $chunkSize);
        }
    }

    /**
     * Benchmark: Random access chunk reading.
     *
     * @Subject
     *
     * @return void
     */
    public function benchRandomAccessRead(): void
    {
        $file = File::new($this->largeFile);
        $fileSize = $file->getSize();
        $chunkSize = 4096;

        // Pre-calculate random offsets to avoid mt_rand overhead in measurement
        $offsets = [
            0,
            1234567,
            $fileSize - 10000,
            5000000,
            $fileSize / 3,
            $fileSize / 2,
            $fileSize * 2 / 3,
            100000,
            50000000,
            $fileSize - $chunkSize,
        ];

        foreach ($offsets as $offset) {
            $file->readMeaningfulBytesAtOffset((int)$offset, $chunkSize);
        }
    }
}
