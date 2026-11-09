<?php

declare(strict_types=1);

namespace Mfonte\SparseDigest\Test\Unit\Cases;

use Mfonte\SparseDigest\File;
use Mfonte\SparseDigest\SparseHasher;
use Mfonte\SparseDigest\Test\Unit\TestCase;

/**
 * SimilarFilesTest
 *
 * Tests specifically designed to validate hash behavior on files that are
 * "almost identical" - simulating real-world scenarios like:
 * - Browser profile databases (Firefox, Chrome)
 * - Log files with appended data
 * - Database files with incremental changes
 * - Binary files with minor patches
 */
class SimilarFilesTest extends TestCase
{
    /**
     * @test
     * Simulates Firefox/Chrome profile databases where only a few bytes change.
     * These are typically SQLite files where header and most structure remain identical.
     */
    public function browser_profile_like_files_with_small_changes(): void
    {
        // Simulate a ~1MB SQLite-like file
        // Header (100 bytes) + structured data + occasional changes
        $header = "SQLite format 3\0" . random_bytes(84); // 100 bytes header
        $structuredData = random_bytes(1024 * 1024 - 200); // ~1MB data
        $footer = random_bytes(100);

        $baseContent = $header . $structuredData . $footer;
        $file1 = $this->createTempFile($baseContent);

        // Create variations with small changes in different locations

        // Version 2: Change only 10 bytes in the middle (like a DB update)
        $middleOffset = 500000;
        $content2 = substr($baseContent, 0, $middleOffset)
            . random_bytes(10)
            . substr($baseContent, $middleOffset + 10);
        $file2 = $this->createTempFile($content2);

        // Version 3: Append 100 bytes (like log growth)
        $content3 = $baseContent . random_bytes(100);
        $file3 = $this->createTempFile($content3);

        // Version 4: Change only the header timestamp (common in DB files)
        $content4 = substr($baseContent, 0, 50)
            . pack('Q', time()) // 8 bytes timestamp
            . substr($baseContent, 58);
        $file4 = $this->createTempFile($content4);

        $hash1 = SparseHasher::new($file1)->hash();
        $hash2 = SparseHasher::new($file2)->hash();
        $hash3 = SparseHasher::new($file3)->hash();
        $hash4 = SparseHasher::new($file4)->hash();

        // File 3 (appended) should definitely differ (size change affects hash)
        $this->assertNotEquals(
            $hash1->toHex(),
            $hash3->toHex(),
            'Appended file should produce different hash due to size change'
        );

        // File 4 (header change) should differ (header is always sampled)
        $this->assertNotEquals(
            $hash1->toHex(),
            $hash4->toHex(),
            'Header-modified file should produce different hash'
        );

        // Note: File 2 (middle change) may or may not differ depending on sampling,
        // so it is intentionally not asserted.

        $this->cleanupFiles([$file1, $file2, $file3, $file4]);
    }

    /**
     * @test
     * Test incremental log file growth pattern.
     */
    public function log_file_incremental_growth(): void
    {
        // Start with a base log content
        $baseLog = str_repeat("2024-12-22 10:00:00 INFO Application started\n", 10000);

        $file1 = $this->createTempFile($baseLog);
        $hash1 = SparseHasher::new($file1)->hash()->toHex();

        // Simulate log growth by appending
        $appendedLog = $baseLog . str_repeat("2024-12-22 11:00:00 DEBUG Processing request\n", 1000);
        $file2 = $this->createTempFile($appendedLog);
        $hash2 = SparseHasher::new($file2)->hash()->toHex();

        $this->assertNotEquals(
            $hash1,
            $hash2,
            'Log with appended data should have different hash'
        );

        // Test: same content, different order (should differ)
        $reorderedLog = str_repeat("2024-12-22 11:00:00 DEBUG Processing request\n", 1000)
            . str_repeat("2024-12-22 10:00:00 INFO Application started\n", 10000);
        $file3 = $this->createTempFile($reorderedLog);
        $hash3 = SparseHasher::new($file3)->hash()->toHex();

        $this->assertNotEquals(
            $hash1,
            $hash3,
            'Reordered log should have different hash'
        );

        $this->cleanupFiles([$file1, $file2, $file3]);
    }

    /**
     * @test
     * Test binary patch simulation - small binary diff in large file.
     */
    public function binary_file_with_patch(): void
    {
        // Create a 2MB "binary" file
        $binaryContent = random_bytes(2 * 1024 * 1024);
        $file1 = $this->createTempFile($binaryContent);

        // Apply patches at various strategic locations
        $patchLocations = [
            0,                  // Start
            4096,               // Second block
            1024 * 1024,        // Middle
            2 * 1024 * 1024 - 4096,  // Second-to-last block
            2 * 1024 * 1024 - 100,   // End
        ];

        $results = [];

        foreach ($patchLocations as $location) {
            $patchedContent = $binaryContent;
            // Apply a 50-byte patch
            $patchedContent = substr($patchedContent, 0, $location)
                . random_bytes(50)
                . substr($patchedContent, $location + 50);

            $patchedFile = $this->createTempFile($patchedContent);
            $patchedHash = SparseHasher::new($patchedFile)->hash()->toHex();
            $originalHash = SparseHasher::new($file1)->hash()->toHex();

            $differs = $patchedHash !== $originalHash;
            $results[$location] = $differs;

            $this->cleanupFile($patchedFile);
        }

        // Patches at start and end should ALWAYS be detected (edge blocks are always sampled)
        $this->assertTrue($results[0], 'Patch at start should be detected');
        $this->assertTrue($results[4096], 'Patch at second block should be detected');
        $this->assertTrue($results[2 * 1024 * 1024 - 4096], 'Patch at second-to-last block should be detected');
        $this->assertTrue($results[2 * 1024 * 1024 - 100], 'Patch at end should be detected');

        $this->cleanupFile($file1);
    }

    /**
     * @test
     * Test media files with metadata changes (common with photos/videos).
     */
    public function media_file_metadata_change(): void
    {
        // Simulate a media file: fixed header (metadata) + large binary payload
        $metadata = "RIFF" . pack('V', 1000000) . "AVI " . random_bytes(4084); // 4KB header
        $payload = random_bytes(1024 * 1024); // 1MB payload

        $file1 = $this->createTempFile($metadata . $payload);

        // Change only metadata (e.g., EXIF date change)
        $modifiedMetadata = "RIFF" . pack('V', 1000000) . "AVI "
            . "Modified" . random_bytes(4076);
        $file2 = $this->createTempFile($modifiedMetadata . $payload);

        $hash1 = SparseHasher::new($file1)->hash();
        $hash2 = SparseHasher::new($file2)->hash();

        $this->assertNotEquals(
            $hash1->toHex(),
            $hash2->toHex(),
            'Metadata change in header should be detected'
        );

        $this->cleanupFiles([$file1, $file2]);
    }

    /**
     * @test
     * Test collision resistance with structurally similar files.
     */
    public function collision_resistance_similar_structure(): void
    {
        // Create 100 files with the same structure but different random data
        $files = [];
        $hashes = [];

        for ($i = 0; $i < 100; $i++) {
            // Same structure: 4KB header + 500KB body + 4KB footer
            $content = random_bytes(4096) . random_bytes(500 * 1024) . random_bytes(4096);
            $file = $this->createTempFile($content);
            $files[] = $file;
            $hashes[] = SparseHasher::new($file)->hash()->toHex();
        }

        // All hashes should be unique
        $uniqueHashes = array_unique($hashes);
        $this->assertCount(
            100,
            $uniqueHashes,
            'All 100 structurally similar files should have unique hashes'
        );

        $this->cleanupFiles($files);
    }

    /**
     * @test
     * Test versioned file detection (like git objects or package versions).
     */
    public function versioned_file_detection(): void
    {
        // Base "package" content
        $version1 = "version=1.0.0\n" . random_bytes(512 * 1024);
        $version2 = "version=1.0.1\n" . random_bytes(512 * 1024);
        $version3 = "version=1.0.0\n" . random_bytes(512 * 1024); // Same version, different content

        $file1 = $this->createTempFile($version1);
        $file2 = $this->createTempFile($version2);
        $file3 = $this->createTempFile($version3);

        $hash1 = SparseHasher::new($file1)->hash()->toHex();
        $hash2 = SparseHasher::new($file2)->hash()->toHex();
        $hash3 = SparseHasher::new($file3)->hash()->toHex();

        // All should differ
        $this->assertNotEquals($hash1, $hash2);
        $this->assertNotEquals($hash1, $hash3);
        $this->assertNotEquals($hash2, $hash3);

        $this->cleanupFiles([$file1, $file2, $file3]);
    }

    /**
     * @test
     * Test that single-bit changes are detected in sampled regions.
     */
    public function single_bit_change_in_sampled_region(): void
    {
        $content = random_bytes(1024 * 1024);
        $file1 = $this->createTempFile($content);

        // Change a single byte in the first block (always sampled)
        $modified = $content;
        $modified[100] = chr(ord($modified[100]) ^ 0x01); // Flip one bit
        $file2 = $this->createTempFile($modified);

        $hash1 = SparseHasher::new($file1)->hash()->toHex();
        $hash2 = SparseHasher::new($file2)->hash()->toHex();

        $this->assertNotEquals(
            $hash1,
            $hash2,
            'Single bit change in sampled region should be detected'
        );

        $this->cleanupFiles([$file1, $file2]);
    }

    /**
     * @test
     * Test file with repeating patterns (compression-resistant content).
     */
    public function file_with_repeating_patterns(): void
    {
        // Create files with highly repetitive content
        $pattern = random_bytes(64);
        $repetitions = 16000; // ~1MB

        $content1 = str_repeat($pattern, $repetitions);
        $content2 = str_repeat($pattern, $repetitions + 1); // One more repetition

        $file1 = $this->createTempFile($content1);
        $file2 = $this->createTempFile($content2);

        $hash1 = SparseHasher::new($file1)->hash()->toHex();
        $hash2 = SparseHasher::new($file2)->hash()->toHex();

        // Different size should produce different hash
        $this->assertNotEquals(
            $hash1,
            $hash2,
            'Files with different repetition counts should differ'
        );

        // Verify determinism with repetitive content
        $hash1Again = SparseHasher::new($file1)->hash()->toHex();
        $this->assertEquals($hash1, $hash1Again);

        $this->cleanupFiles([$file1, $file2]);
    }

    /**
     * Helper to create a temp file with given content.
     */
    protected function createTempFile(string $content): string
    {
        $path = sys_get_temp_dir() . '/sparsedigest_similar_' . uniqid() . '.bin';
        file_put_contents($path, $content);

        return $path;
    }

    /**
     * Helper to cleanup a temp file.
     */
    protected function cleanupFile(string $path): void
    {
        if (file_exists($path)) {
            unlink($path);
        }
    }

    /**
     * Helper to cleanup multiple files.
     *
     * @param array<string> $paths
     */
    protected function cleanupFiles(array $paths): void
    {
        foreach ($paths as $path) {
            $this->cleanupFile($path);
        }
    }
}
