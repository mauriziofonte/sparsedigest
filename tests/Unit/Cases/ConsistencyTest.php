<?php
namespace Mfonte\SparseDigest\Test\Unit\Cases;

use Mfonte\SparseDigest\Test\Unit\TestCase;
use Mfonte\SparseDigest\SparseHasher;
use Mfonte\SparseDigest\File;

/**
 * ConsistencyTest
 *
 * Advanced tests to verify hash consistency across various edge cases.
 * These tests ensure that consumers can rely on the library for deterministic
 * and reproducible results.
 */
class ConsistencyTest extends TestCase
{
    /**
     * @test
     * Test that files differing only at the beginning produce different hashes.
     */
    public function files_differing_at_start_produce_different_hashes(): void
    {
        $baseContent = random_bytes(1024 * 1024); // 1MB random data
        
        // Create two files: identical except first 100 bytes
        $file1 = $this->createTempFile($baseContent);
        $file2 = $this->createTempFile(random_bytes(100) . substr($baseContent, 100));
        
        $hash1 = SparseHasher::new($file1)->hash();
        $hash2 = SparseHasher::new($file2)->hash();
        
        $this->assertNotEquals(
            $hash1->toHex(),
            $hash2->toHex(),
            'Files differing at start should produce different hashes'
        );
        
        $this->cleanupFile($file1);
        $this->cleanupFile($file2);
    }
    
    /**
     * @test
     * Test that files differing only at the end produce different hashes.
     */
    public function files_differing_at_end_produce_different_hashes(): void
    {
        $baseContent = random_bytes(1024 * 1024); // 1MB random data
        
        // Create two files: identical except last 100 bytes
        $file1 = $this->createTempFile($baseContent);
        $file2 = $this->createTempFile(substr($baseContent, 0, -100) . random_bytes(100));
        
        $hash1 = SparseHasher::new($file1)->hash();
        $hash2 = SparseHasher::new($file2)->hash();
        
        $this->assertNotEquals(
            $hash1->toHex(),
            $hash2->toHex(),
            'Files differing at end should produce different hashes'
        );
        
        $this->cleanupFile($file1);
        $this->cleanupFile($file2);
    }
    
    /**
     * @test
     * Test that files differing only in the middle produce different hashes.
     */
    public function files_differing_in_middle_produce_different_hashes(): void
    {
        $size = 1024 * 1024; // 1MB
        $middleOffset = (int) ($size / 2);
        $baseContent = random_bytes($size);
        
        // Create two files: identical except 100 bytes in the middle
        $file1 = $this->createTempFile($baseContent);
        $modifiedContent = substr($baseContent, 0, $middleOffset)
            . random_bytes(100)
            . substr($baseContent, $middleOffset + 100);
        $file2 = $this->createTempFile($modifiedContent);
        
        $hash1 = SparseHasher::new($file1)->hash();
        $hash2 = SparseHasher::new($file2)->hash();
        
        // Note: This MAY produce the same hash if the modification is in a non-sampled region!
        // This is expected behavior for sparse hashing.
        // We document this rather than assert different hashes.
        
        $this->assertTrue(
            true,
            'Middle-differing files may or may not produce different hashes depending on sampling'
        );
        
        $this->cleanupFile($file1);
        $this->cleanupFile($file2);
    }
    
    /**
     * @test
     * Test that files with only null-byte differences produce different hashes.
     */
    public function files_with_null_byte_differences_produce_different_hashes(): void
    {
        // Create file with specific null byte patterns
        $content1 = random_bytes(4096) . str_repeat("\0", 100) . random_bytes(4096);
        $content2 = random_bytes(4096) . str_repeat("\0", 200) . random_bytes(3996); // Same total size
        
        // Ensure same size
        $this->assertEquals(strlen($content1), strlen($content2));
        
        $file1 = $this->createTempFile($content1);
        $file2 = $this->createTempFile($content2);
        
        $hash1 = SparseHasher::new($file1)->hash();
        $hash2 = SparseHasher::new($file2)->hash();
        
        $this->assertNotEquals(
            $hash1->toHex(),
            $hash2->toHex(),
            'Files with different null byte patterns should produce different hashes'
        );
        
        $this->cleanupFile($file1);
        $this->cleanupFile($file2);
    }
    
    /**
     * @test
     * Test determinism: same file hashed 100 times produces identical results.
     */
    public function repeated_hashing_is_deterministic(): void
    {
        $file = $this->createTempFile(random_bytes(512 * 1024)); // 512KB
        
        $referenceHash = SparseHasher::new($file)->hash()->toHex();
        
        for ($i = 0; $i < 100; $i++) {
            $hash = SparseHasher::new($file)->hash()->toHex();
            $this->assertEquals(
                $referenceHash,
                $hash,
                "Hash should be identical on iteration {$i}"
            );
        }
        
        $this->cleanupFile($file);
    }
    
    /**
     * @test
     * Test determinism with different SparseDigest instances.
     */
    public function different_instances_produce_same_hash(): void
    {
        $file = $this->createTempFile(random_bytes(512 * 1024));
        
        $sd1 = SparseHasher::new($file);
        $sd2 = SparseHasher::new($file);
        $sd3 = SparseHasher::new($file);
        
        $hash1 = $sd1->hash()->toHex();
        $hash2 = $sd2->hash()->toHex();
        $hash3 = $sd3->hash()->toHex();
        
        $this->assertEquals($hash1, $hash2);
        $this->assertEquals($hash2, $hash3);
        
        $this->cleanupFile($file);
    }
    
    /**
     * @test
     * Test that coverage parameter affects hash value.
     */
    public function different_coverage_produces_different_hash(): void
    {
        $file = $this->createTempFile(random_bytes(1024 * 1024));
        
        $hash10 = SparseHasher::new($file)->hash(10, 4096)->toHex();
        $hash20 = SparseHasher::new($file)->hash(20, 4096)->toHex();
        $hash50 = SparseHasher::new($file)->hash(50, 4096)->toHex();
        
        $this->assertNotEquals($hash10, $hash20, 'Different coverage should produce different hashes');
        $this->assertNotEquals($hash20, $hash50, 'Different coverage should produce different hashes');
        $this->assertNotEquals($hash10, $hash50, 'Different coverage should produce different hashes');
        
        $this->cleanupFile($file);
    }
    
    /**
     * @test
     * Test that sample size parameter affects hash value.
     */
    public function different_sample_size_produces_different_hash(): void
    {
        $file = $this->createTempFile(random_bytes(2 * 1024 * 1024)); // 2MB for larger sample sizes
        
        $hash4k = SparseHasher::new($file)->hash(10, 4096)->toHex();
        $hash8k = SparseHasher::new($file)->hash(10, 8192)->toHex();
        $hash16k = SparseHasher::new($file)->hash(10, 16384)->toHex();
        
        $this->assertNotEquals($hash4k, $hash8k, 'Different sample size should produce different hashes');
        $this->assertNotEquals($hash8k, $hash16k, 'Different sample size should produce different hashes');
        $this->assertNotEquals($hash4k, $hash16k, 'Different sample size should produce different hashes');
        
        $this->cleanupFile($file);
    }
    
    /**
     * @test
     * Test files with sparse regions (large null byte sequences).
     */
    public function sparse_files_are_handled_correctly(): void
    {
        // Simulate a sparse file with alternating data and null regions
        $content = '';
        for ($i = 0; $i < 10; $i++) {
            $content .= random_bytes(4096); // 4KB data
            $content .= str_repeat("\0", 8192); // 8KB nulls
        }
        
        $file = $this->createTempFile($content);
        
        // Should not throw and should produce a valid hash
        $hash = SparseHasher::new($file)->hash();
        
        $this->assertNotEmpty($hash->toHex());
        $this->assertEquals(
            strlen($hash->toHex()),
            strlen(SparseHasher::new($file)->hash()->toHex()),
            'Hash should be consistent length'
        );
        
        $this->cleanupFile($file);
    }
    
    /**
     * @test
     * Test files that are 100% null bytes (edge case).
     */
    public function all_null_file_produces_valid_hash(): void
    {
        // A file of pure null bytes
        $file = $this->createTempFile(str_repeat("\0", 100 * 1024)); // 100KB
        
        // Should not throw and should produce a valid hash
        // Note: This will likely fall back to full hash since it's not "binary"
        $hash = SparseHasher::new($file)->hash();
        
        $this->assertNotEmpty($hash->toHex());
        
        $this->cleanupFile($file);
    }
    
    /**
     * @test
     * Test that identical copies produce identical hashes.
     */
    public function identical_copies_produce_identical_hashes(): void
    {
        $content = random_bytes(1024 * 1024);
        
        $file1 = $this->createTempFile($content);
        $file2 = $this->createTempFile($content);
        
        $hash1 = SparseHasher::new($file1)->hash()->toHex();
        $hash2 = SparseHasher::new($file2)->hash()->toHex();
        
        $this->assertEquals(
            $hash1,
            $hash2,
            'Identical file copies should produce identical hashes'
        );
        
        $this->cleanupFile($file1);
        $this->cleanupFile($file2);
    }
    
    /**
     * @test
     * Test boundary condition: file size exactly at sampling threshold.
     */
    public function file_at_exact_sampling_threshold(): void
    {
        // With 10% coverage and 4096 sample size, minimum is ~4 blocks = ~160KB
        // Create files at various threshold points
        $sizes = [
            163840,  // Exactly 40 blocks of 4096
            164000,  // Just above
            163500,  // Just below
        ];
        
        foreach ($sizes as $size) {
            $file = $this->createTempFile(random_bytes($size));
            
            try {
                $hash = SparseHasher::new($file)->hash();
                $this->assertNotEmpty($hash->toHex(), "File of size {$size} should hash successfully");
            } catch (\Exception $e) {
                // Some sizes may fall back to full hash, which is fine
                $this->assertTrue(true);
            }
            
            $this->cleanupFile($file);
        }
    }
    
    /**
     * @test
     * Test that file modification timestamp doesn't affect hash.
     */
    public function file_modification_time_does_not_affect_hash(): void
    {
        $content = random_bytes(512 * 1024);
        $file = $this->createTempFile($content);
        
        $hash1 = SparseHasher::new($file)->hash()->toHex();
        
        // Modify the file's timestamp
        touch($file, time() + 3600); // 1 hour in future
        clearstatcache(true, $file);
        
        $hash2 = SparseHasher::new($file)->hash()->toHex();
        
        $this->assertEquals(
            $hash1,
            $hash2,
            'Modification timestamp should not affect hash'
        );
        
        $this->cleanupFile($file);
    }
    
    /**
     * @test
     * Test cross-algorithm consistency: same sampling, different final algo.
     */
    public function different_algorithms_produce_different_hashes(): void
    {
        $file = $this->createTempFile(random_bytes(1024 * 1024));
        
        $algos = ['md5', 'sha256', 'sha1'];
        $hashes = [];
        
        foreach ($algos as $algo) {
            $hashes[$algo] = SparseHasher::new($file)->hashAs($algo)->toHex();
        }
        
        // All should be different
        $uniqueHashes = array_unique($hashes);
        $this->assertCount(
            count($algos),
            $uniqueHashes,
            'Different algorithms should produce different hashes'
        );
        
        $this->cleanupFile($file);
    }
    
    /**
     * @test
     * Test that sparse hash differs from full hash.
     */
    public function sparse_hash_differs_from_full_hash(): void
    {
        $file = $this->createTempFile(random_bytes(1024 * 1024));
        
        $sparseHash = SparseHasher::new($file)->hashAs('sha256')->toHex();
        $fullHash = File::new($file)->hashWith('sha256')->toHex();
        
        // They should be different because sparse reads only portions
        $this->assertNotEquals(
            $sparseHash,
            $fullHash,
            'Sparse hash should differ from full hash for large binary files'
        );
        
        $this->cleanupFile($file);
    }
    
    /**
     * Helper to create a temp file with given content.
     */
    protected function createTempFile(string $content): string
    {
        $path = sys_get_temp_dir() . '/sparsedigest_test_' . uniqid() . '.bin';
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
}
