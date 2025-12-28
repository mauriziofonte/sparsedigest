<?php
namespace Mfonte\SparseDigest\Test\Unit\Cases;

use Mfonte\SparseDigest\Test\Unit\TestCase;
use Mfonte\SparseDigest\File;

/**
 * ReadMeaningfulBytesTest
 *
 * Critical tests to verify the correctness and reliability of the
 * readMeaningfulBytesAtOffset() method. This is the core of the sparse
 * digest algorithm and must be 100% reliable.
 */
class ReadMeaningfulBytesTest extends TestCase
{
    /**
     * @test
     * Test basic reading at offset 0.
     */
    public function read_at_offset_zero(): void
    {
        $content = random_bytes(10000);
        $file = $this->createTempFile($content);
        
        $file = File::new($file);
        $data = $file->readMeaningfulBytesAtOffset(0, 4096);
        
        // Should return exactly 4096 bytes
        $this->assertEquals(4096, strlen($data));
        
        $this->cleanupFile($file);
    }
    
    /**
     * @test
     * Test reading at various offsets.
     */
    public function read_at_various_offsets(): void
    {
        $content = random_bytes(100000);
        $file = $this->createTempFile($content);
        
        $file = File::new($file);
        
        $offsets = [0, 4096, 8192, 50000, 95000];
        
        foreach ($offsets as $offset) {
            $data = $file->readMeaningfulBytesAtOffset($offset, 4096);
            $this->assertEquals(
                4096,
                strlen($data),
                "Read at offset {$offset} should return 4096 bytes"
            );
        }
        
        $this->cleanupFile($file);
    }
    
    /**
     * @test
     * Test that reading the same offset twice returns identical data.
     */
    public function read_same_offset_returns_identical_data(): void
    {
        $content = random_bytes(50000);
        $file = $this->createTempFile($content);
        
        $file = File::new($file);
        
        $data1 = $file->readMeaningfulBytesAtOffset(4096, 4096);
        $data2 = $file->readMeaningfulBytesAtOffset(4096, 4096);
        
        $this->assertEquals(
            $data1,
            $data2,
            'Reading same offset twice should return identical data'
        );
        
        $this->cleanupFile($file);
    }
    
    /**
     * @test
     * Test reading file with null bytes produces consistent results.
     */
    public function read_file_with_null_bytes(): void
    {
        // Create file with specific null byte pattern
        $content = random_bytes(4096) . str_repeat("\0", 1000) . random_bytes(5000);
        $file = $this->createTempFile($content);
        
        $file = File::new($file);
        
        // Read across the null byte region
        $data = $file->readMeaningfulBytesAtOffset(4000, 4096);
        
        // Should return 4096 bytes (null bytes replaced with length)
        $this->assertEquals(4096, strlen($data));
        
        // Read again should be identical
        $data2 = $file->readMeaningfulBytesAtOffset(4000, 4096);
        $this->assertEquals($data, $data2);
        
        $this->cleanupFile($file);
    }
    
    /**
     * @test
     * Test reading at boundary (offset + length = file size).
     */
    public function read_at_exact_boundary(): void
    {
        $size = 8192;
        $content = random_bytes($size);
        $file = $this->createTempFile($content);
        
        $file = File::new($file);
        
        // Read last 4096 bytes
        $data = $file->readMeaningfulBytesAtOffset($size - 4096, 4096);
        
        $this->assertEquals(4096, strlen($data));
        
        $this->cleanupFile($file);
    }
    
    /**
     * @test
     * Test that exceeding file bounds throws exception.
     */
    public function read_exceeding_bounds_throws(): void
    {
        $content = random_bytes(5000);
        $file = $this->createTempFile($content);
        
        $file = File::new($file);
        
        $this->expectException(\InvalidArgumentException::class);
        $file->readMeaningfulBytesAtOffset(4000, 4096); // 4000 + 4096 = 8096 > 5000
        
        $this->cleanupFile($file);
    }
    
    /**
     * @test
     * Test negative offset throws exception.
     */
    public function read_negative_offset_throws(): void
    {
        $content = random_bytes(10000);
        $file = $this->createTempFile($content);
        
        $file = File::new($file);
        
        $this->expectException(\InvalidArgumentException::class);
        $file->readMeaningfulBytesAtOffset(-100, 4096);
        
        $this->cleanupFile($file);
    }
    
    /**
     * @test
     * Test zero length throws exception.
     */
    public function read_zero_length_throws(): void
    {
        $content = random_bytes(10000);
        $file = $this->createTempFile($content);
        
        $file = File::new($file);
        
        $this->expectException(\InvalidArgumentException::class);
        $file->readMeaningfulBytesAtOffset(0, 0);
        
        $this->cleanupFile($file);
    }
    
    /**
     * @test
     * Test caching works correctly with different offset/length combinations.
     */
    public function caching_works_with_different_parameters(): void
    {
        $content = random_bytes(50000);
        $file = $this->createTempFile($content);
        
        $file = File::new($file);
        
        // Read with different lengths at same offset
        $data1 = $file->readMeaningfulBytesAtOffset(0, 4096);
        $data2 = $file->readMeaningfulBytesAtOffset(0, 8192);
        
        // They should be different (different cache keys)
        $this->assertNotEquals(
            $data1,
            $data2,
            'Different lengths at same offset should produce different results'
        );
        $this->assertEquals(4096, strlen($data1));
        $this->assertEquals(8192, strlen($data2));
        
        // Re-reading should use cache and return same values
        $data1Again = $file->readMeaningfulBytesAtOffset(0, 4096);
        $data2Again = $file->readMeaningfulBytesAtOffset(0, 8192);
        
        $this->assertEquals($data1, $data1Again);
        $this->assertEquals($data2, $data2Again);
        
        $this->cleanupFile($file);
    }
    
    /**
     * @test
     * Test reading sparse file (mostly null bytes).
     */
    public function read_sparse_file(): void
    {
        // File with alternating data and null regions
        $content = random_bytes(1000)
            . str_repeat("\0", 8000)
            . random_bytes(1000);
        $file = $this->createTempFile($content);
        
        $file = File::new($file);
        
        // Read from the null region
        $data = $file->readMeaningfulBytesAtOffset(1000, 4096);
        
        // Should still return 4096 bytes
        $this->assertEquals(4096, strlen($data));
        
        $this->cleanupFile($file);
    }
    
    /**
     * @test
     * Test determinism across multiple Digestable instances.
     */
    public function determinism_across_instances(): void
    {
        $content = random_bytes(50000);
        $file = $this->createTempFile($content);
        
        $file1 = File::new($file);
        $file2 = File::new($file);
        
        $data1 = $file1->readMeaningfulBytesAtOffset(10000, 4096);
        $data2 = $file2->readMeaningfulBytesAtOffset(10000, 4096);
        
        $this->assertEquals(
            $data1,
            $data2,
            'Different instances reading same offset should return identical data'
        );
        
        $this->cleanupFile($file);
    }
    
    /**
     * @test
     * Test reading entire small file.
     */
    public function read_entire_small_file(): void
    {
        $content = random_bytes(4096);
        $file = $this->createTempFile($content);
        
        $file = File::new($file);
        $data = $file->readMeaningfulBytesAtOffset(0, 4096);
        
        $this->assertEquals(4096, strlen($data));
        
        $this->cleanupFile($file);
    }
    
    /**
     * @test
     * Test that null byte replacement is deterministic.
     */
    public function null_byte_replacement_is_deterministic(): void
    {
        // Create file with known null byte pattern
        $content = "HEADER" . str_repeat("\0", 100) . "DATA" . str_repeat("\0", 50) . "FOOTER";
        // Pad to 4096+ bytes
        $content .= random_bytes(4096 - strlen($content));
        $content .= random_bytes(4096); // Additional block
        
        $file = $this->createTempFile($content);
        
        $file1 = File::new($file);
        $file2 = File::new($file);
        
        $data1 = $file1->readMeaningfulBytesAtOffset(0, 4096);
        $data2 = $file2->readMeaningfulBytesAtOffset(0, 4096);
        
        $this->assertEquals(
            $data1,
            $data2,
            'Null byte replacement should be deterministic'
        );
        
        $this->cleanupFile($file);
    }
    
    /**
     * @test
     * Test different null byte sequence lengths produce different results.
     */
    public function different_null_sequences_produce_different_data(): void
    {
        // Two files with same content but different null byte sequences
        $base = random_bytes(1000);
        $content1 = $base . str_repeat("\0", 100) . random_bytes(3000);
        $content2 = $base . str_repeat("\0", 200) . random_bytes(2900); // Same size, different null pattern
        
        $file1 = $this->createTempFile($content1);
        $file2 = $this->createTempFile($content2);
        
        $data1 = File::new($file1)->readMeaningfulBytesAtOffset(0, 4096);
        $data2 = File::new($file2)->readMeaningfulBytesAtOffset(0, 4096);
        
        // Should produce different data due to null byte length encoding
        $this->assertNotEquals(
            $data1,
            $data2,
            'Different null byte sequence lengths should produce different encoded data'
        );
        
        $this->cleanupFiles([$file1, $file2]);
    }
    
    /**
     * @test
     * Test reading with various sample sizes (all must be divisible by 64).
     */
    public function read_with_various_sample_sizes(): void
    {
        $content = random_bytes(100000);
        $file = $this->createTempFile($content);
        
        $file = File::new($file);
        
        $sampleSizes = [64, 128, 256, 512, 1024, 2048, 4096, 8192, 16384];
        
        foreach ($sampleSizes as $size) {
            $data = $file->readMeaningfulBytesAtOffset(0, $size);
            $this->assertEquals(
                $size,
                strlen($data),
                "Sample size {$size} should return exactly {$size} bytes"
            );
        }
        
        $this->cleanupFile($file);
    }
    
    /**
     * Helper to create a temp file with given content.
     */
    protected function createTempFile(string $content): string
    {
        $path = sys_get_temp_dir() . '/sparsedigest_read_' . uniqid() . '.bin';
        file_put_contents($path, $content);
        return $path;
    }
    
    /**
     * Helper to cleanup a temp file.
     */
    protected function cleanupFile(string $path): void
    {
        if (file_exists($path)) {
            @unlink($path);
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
