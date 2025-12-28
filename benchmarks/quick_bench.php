<?php
/**
 * Quick benchmark for iterative optimization testing.
 * Usage: php benchmarks/quick_bench.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Mfonte\SparseDigest\SparseHasher;
use Mfonte\SparseDigest\File;

// Create test files of 5MB and 10MB
$testDir = sys_get_temp_dir() . '/sparsedigest_quick_bench';
if (!is_dir($testDir)) {
    mkdir($testDir, 0755, true);
}

$files = [
    '256KB' => $testDir . '/test_256kb.bin',
    '512KB' => $testDir . '/test_512kb.bin',
    '768KB' => $testDir . '/test_768kb.bin',
    '1MB' => $testDir . '/test_1mb.bin',
    '2MB' => $testDir . '/test_2mb.bin',
    '5MB' => $testDir . '/test_5mb.bin',
];

foreach ($files as $label => $path) {
    if (!file_exists($path)) {
        $sizes = [
            '256KB' => 256 * 1024,
            '512KB' => 512 * 1024,
            '768KB' => 768 * 1024,
            '1MB' => 1024 * 1024,
            '2MB' => 2 * 1024 * 1024,
            '5MB' => 5 * 1024 * 1024,
        ];
        $size = $sizes[$label];
        echo "Creating {$label} test file...\n";
        $fp = fopen($path, 'wb');
        $chunk = random_bytes(8192);
        $written = 0;
        while ($written < $size) {
            fwrite($fp, $chunk);
            $written += 8192;
        }
        fclose($fp);
    }
}

echo "\n=== Quick Benchmark (5MB and 10MB files) ===\n\n";

$iterations = 5;

foreach ($files as $label => $path) {
    echo "--- {$label} File ---\n";
    
    // Warm up filesystem cache
    file_get_contents($path, false, null, 0, 1);
    
    // 1. Native hash_file (xxh3)
    $times = [];
    for ($i = 0; $i < $iterations; $i++) {
        $start = hrtime(true);
        hash_file('xxh3', $path);
        $times[] = (hrtime(true) - $start) / 1_000_000;
    }
    $avgNative = array_sum($times) / count($times);
    $memBefore = memory_get_usage(true);
    
    // 2. SparseDigest
    $times = [];
    $peakMem = 0;
    for ($i = 0; $i < $iterations; $i++) {
        gc_collect_cycles();
        $memStart = memory_get_usage(true);
        
        $start = hrtime(true);
        $sd = SparseHasher::new($path);
        $digest = $sd->hash();
        $times[] = (hrtime(true) - $start) / 1_000_000;
        
        $peakMem = max($peakMem, memory_get_usage(true) - $memStart);
        unset($sd, $digest);
    }
    $avgSparse = array_sum($times) / count($times);
    
    // 3. Breakdown: isLikelyBinary only
    $times = [];
    for ($i = 0; $i < $iterations; $i++) {
        $digestable = File::new($path);
        $start = hrtime(true);
        $digestable->isLikelyBinary();
        $times[] = (hrtime(true) - $start) / 1_000_000;
    }
    $avgBinaryCheck = array_sum($times) / count($times);
    
    // 4. Breakdown: cantBeSampled only
    $times = [];
    for ($i = 0; $i < $iterations; $i++) {
        $digestable = File::new($path);
        $start = hrtime(true);
        $digestable->cantBeSampled(10, 4096);
        $times[] = (hrtime(true) - $start) / 1_000_000;
    }
    $avgCantBeSampled = array_sum($times) / count($times);
    
    // Results
    printf("  Native hash_file(xxh3):    %8.3f ms\n", $avgNative);
    printf(
        "  SparseHasher::hash():      %8.3f ms (%.1fx %s)\n",
        $avgSparse,
        $avgSparse > $avgNative ? $avgSparse / $avgNative : $avgNative / $avgSparse,
        $avgSparse > $avgNative ? 'SLOWER' : 'faster'
    );
    printf("  └─ isLikelyBinary():       %8.3f ms\n", $avgBinaryCheck);
    printf("  └─ cantBeSampled():        %8.3f ms\n", $avgCantBeSampled);
    printf("  Memory overhead:           %8.2f KB\n", $peakMem / 1024);
    echo "\n";
}

// Cleanup
echo "Cleaning up test files...\n";
foreach ($files as $path) {
    if (file_exists($path)) {
        unlink($path);
    }
}
rmdir($testDir);

echo "Done.\n";
