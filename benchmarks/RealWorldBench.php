<?php

declare(strict_types=1);

namespace Mfonte\SparseDigest\Benchmarks;

use Mfonte\SparseDigest\File;
use Mfonte\SparseDigest\SparseHasher;

/**
 * Benchmark: Real-World Use Cases
 *
 * This benchmark simulates real-world scenarios where SparseDigest shines:
 * - Duplicate detection in media libraries
 * - Quick file change detection
 * - Batch processing of many files
 *
 * @Groups({"realworld", "usecases"})
 * @BeforeMethods({"setUp"})
 * @Revs(3)
 * @Iterations(3)
 * @Warmup(1)
 */
class RealWorldBench extends AbstractBenchmark
{
    /** @var array<string, string> */
    private $mediaFiles = [];

    /** @var array<string, string> */
    private $documentFiles = [];

    /** @var string */
    private $largeLogFile;

    /**
     * Set up test files simulating real-world scenarios.
     *
     * @return void
     */
    public function setUp(): void
    {
        // Simulate media files (images, videos) - binary content
        $mediaSizes = [
            'small_image' => 500 * 1024,          // 500KB JPEG
            'medium_image' => 3 * 1024 * 1024,    // 3MB RAW
            'small_video' => 50 * 1024 * 1024,    // 50MB video clip
            'large_video' => 200 * 1024 * 1024,   // 200MB video
        ];

        foreach ($mediaSizes as $label => $size) {
            $this->mediaFiles[$label] = $this->createTempFile($size, "media_{$label}");
        }

        // Simulate document files - text content
        $docSizes = [
            'small_doc' => 50 * 1024,             // 50KB document
            'large_doc' => 500 * 1024,            // 500KB document
            'ebook' => 5 * 1024 * 1024,           // 5MB ebook
        ];

        foreach ($docSizes as $label => $size) {
            $this->documentFiles[$label] = $this->createTempTextFile($size, "doc_{$label}");
        }

        // Large log file
        $this->largeLogFile = $this->createTempTextFile(100 * 1024 * 1024, 'large_log');
    }

    // =========================================================================
    // MEDIA FILE DEDUPLICATION
    // =========================================================================

    /**
     * Benchmark: Hash a batch of media files with SparseDigest.
     * Simulates scanning a photo library for duplicates.
     *
     * @Subject
     *
     * @return void
     */
    public function benchMediaBatchSparse(): void
    {
        foreach ($this->mediaFiles as $file) {
            $sd = SparseHasher::new($file);
            $sd->hash(10, 4096);
        }
    }

    /**
     * Benchmark: Hash a batch of media files with hash_file.
     * Baseline comparison.
     *
     * @Subject
     *
     * @return void
     */
    public function benchMediaBatchNative(): void
    {
        foreach ($this->mediaFiles as $file) {
            hash_file('xxh3', $file);
        }
    }

    // =========================================================================
    // DOCUMENT PROCESSING
    // =========================================================================

    /**
     * Benchmark: Hash a batch of documents with SparseDigest.
     *
     * @Subject
     *
     * @return void
     */
    public function benchDocumentBatchSparse(): void
    {
        foreach ($this->documentFiles as $file) {
            $sd = SparseHasher::new($file);
            $sd->hash(10, 4096);
        }
    }

    /**
     * Benchmark: Hash a batch of documents with hash_file.
     *
     * @Subject
     *
     * @return void
     */
    public function benchDocumentBatchNative(): void
    {
        foreach ($this->documentFiles as $file) {
            hash_file('xxh3', $file);
        }
    }

    // =========================================================================
    // LARGE LOG FILE ANALYSIS
    // =========================================================================

    /**
     * Benchmark: Quick fingerprint of a large log file with SparseDigest.
     * Useful for detecting if logs have changed.
     *
     * @Subject
     *
     * @return void
     */
    public function benchLargeLogSparse(): void
    {
        $sd = SparseHasher::new($this->largeLogFile);
        $sd->hash(5, 8192); // Lower coverage, larger blocks for speed
    }

    /**
     * Benchmark: Full hash of a large log file.
     *
     * @Subject
     *
     * @return void
     */
    public function benchLargeLogNative(): void
    {
        hash_file('xxh3', $this->largeLogFile);
    }

    // =========================================================================
    // REPEATED HASHING (CACHE WARMTH)
    // =========================================================================

    /**
     * Benchmark: Hash the same large file multiple times with SparseDigest.
     * Tests internal caching behavior.
     *
     * @Subject
     *
     * @return void
     */
    public function benchRepeatedHashSparse(): void
    {
        $file = $this->mediaFiles['large_video'];
        $sd = SparseHasher::new($file);

        // Hash with different parameters
        $sd->hash(5, 4096);
        $sd->hash(10, 4096);
        $sd->hash(15, 4096);
    }

    /**
     * Benchmark: Hash the same large file multiple times with hash_file.
     *
     * @Subject
     *
     * @return void
     */
    public function benchRepeatedHashNative(): void
    {
        $file = $this->mediaFiles['large_video'];

        hash_file('xxh3', $file);
        hash_file('xxh3', $file);
        hash_file('xxh3', $file);
    }
}
