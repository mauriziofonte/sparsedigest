<?php

////////////////////////////////////////////////////////////////////////////////
//                         CONSTANTS & INITIAL SETUP                          //
////////////////////////////////////////////////////////////////////////////////

define('SCRIPT', realpath($argv[0]));
define('DS', DIRECTORY_SEPARATOR);
define('TD', rtrim(__DIR__, DS) . DS);
define('SRC', realpath(TD . '..' . DS) . DS . 'src' . DS);

// Manually load the SparseDigest classes
require_once SRC . 'Digest.php';
require_once SRC . 'Digestable.php';
require_once SRC . 'Implementation.php';
require_once SRC . 'SparseDigest.php';
require_once SRC . 'Hasher/Base.php';
require_once SRC . 'Hasher/Sparse.php';

$algorithms = [
    'xxh3', 'xxh128', 'crc32c', 'xxh64', 'crc32', 'crc32b', 'murmur3f', 'xxh32',
    'murmur3c', 'murmur3a', 'adler32', 'md4', 'fnv1a64', 'fnv132', 'fnv164', 'fnv1a32',
    'joaat', 'md5', 'tiger128,3', 'tiger160,3', 'tiger192,3', 'sha1', 'tiger160,4',
    'tiger128,4', 'tiger192,4', 'ripemd128', 'ripemd256', 'sha3-224', 'sha3-256',
    'sha384', 'sha512/224', 'sha512/256', 'sha512', 'ripemd320', 'ripemd160', 'sha3-384',
    //'haval128,3', 'haval160,3', 'haval192,3', 'haval224,3', 'haval256,3', 'sha224',
    //'sha256', 'sha3-512', 'haval128,4', 'haval160,4', 'haval192,4', 'haval224,4',
    //'haval256,4', 'haval128,5', 'haval160,5', 'haval192,5', 'haval224,5', 'haval256,5',
    //'whirlpool', 'gost', 'gost-crypto', 'snefru', 'snefru256', 'md2'
];

$buckets = [
    ['0-10KB', 0, 10_000],
    ['10-20KB', 10_000, 20_000],
    ['20-30KB', 20_000, 30_000],
    ['30-50KB', 30_000, 50_000],
    ['50-70KB', 50_000, 70_000],
    ['70-100KB', 70_000, 100_000],
    ['100-200KB', 100_000, 200_000],
    ['200-500KB', 200_000, 500_000],
    ['500KB-1MB', 500_000, 1_000_000],
    ['1-2MB', 1_000_000, 2_000_000],
    ['2-4MB', 2_000_000, 4_000_000],
    ['4-8MB', 4_000_000, 8_000_000],
    ['8-16MB', 8_000_000, 16_000_000],
    ['16-32MB', 16_000_000, 32_000_000],
    ['32-64MB', 32_000_000, 64_000_000],
    ['64-128MB', 64_000_000, 128_000_000],
    ['128-256MB', 128_000_000, 256_000_000],
];

$aggregates = [];
foreach ($algorithms as $algo) {
    echo "## Benchmark results for Hashing Algo " . strtoupper($algo) . "\n\n";
    echo "This test shows the performance of the SparseDigest `hashWith('{$algo}')` method compared to the native `hash_file('{$algo}')` method.\n\n";

    $tableData = [];
    $colWidths = [
        strlen("Bucket"),
        strlen("SparseHash ms"),
        strlen("FullHash ms"),
        strlen("Performance Gain"),
    ];

    foreach ($buckets as $bucket) {
        $label = $bucket[0];
        $minSize = $bucket[1];
        $maxSize = $bucket[2];
        
        if (!array_key_exists($label, $aggregates)) {
            $aggregates[$label] = ['sparse'=> [], 'full' => []];
        }

        $fileSize = rand($minSize, $maxSize === null ? PHP_INT_MAX : $maxSize); // Random size within bucket
        $tempFile = tempnam(sys_get_temp_dir(), 'sparsedigest_bench_');

        if ($tempFile === false) {
            die("Failed to create temporary file.\n");
        }

        $handle = fopen($tempFile, 'wb');
        if ($handle === false) {
            die("Failed to open temporary file.\n");
        }
        fwrite($handle, random_bytes($fileSize));
        fclose($handle);

        $fileObj = \Mfonte\SparseDigest\File::new($tempFile);
        $sparseHasher = \Mfonte\SparseDigest\SparseHasher::new($tempFile);
        $fileHasher = \Mfonte\SparseDigest\FileHasher::new($tempFile);

        try {
            $startSparse = microtime(true);
            $sparseHash = $sparseHasher->hashAs($algo);
            $sparseTime = (microtime(true) - $startSparse) * 1000;
            
            $startFull = microtime(true);
            $fullHash = $fileHasher->hashAs($algo);
            $fullTime = (microtime(true) - $startFull) * 1000;

            $gain = ($sparseTime > 0) ? ($fullTime / $sparseTime) : 0;
            $percentage = round(($gain - 1) * 100, 2);
            $gainStr = sprintf("%.2fx (%+.2f%%)", $gain, $percentage);

            $aggregates[$label]['sparse'][] = $sparseTime;
            $aggregates[$label]['full'][] = $fullTime;

            $row = [
                $bucket[0],
                sprintf("%.4fms", $sparseTime),
                sprintf("%.4fms", $fullTime),
                $gainStr,
            ];
            $tableData[] = $row;

            // Update column widths based on current row data
            for ($i = 0; $i < count($row); $i++) {
                $colWidths[$i] = max($colWidths[$i], strlen($row[$i]));
            }

        } catch (\Exception $e) {
            $row = [
                $bucket[0],
                "Error",
                "Error",
                "Error: " . $e->getMessage(),
            ];
            $tableData[] = $row;
            for ($i = 0; $i < count($row); $i++) {
                $colWidths[$i] = max($colWidths[$i], strlen($row[$i]));
            }
        }

        unlink($tempFile);
    }
    
    echo '| ' . str_pad("Bucket", $colWidths[0]) . " | " .
         str_pad("SparseHash", $colWidths[1]) . " | " .
         str_pad("Native", $colWidths[2]) . " | " .
         str_pad("Performance Gain", $colWidths[3]) . " |\n";

    echo '| ' . str_repeat("-", $colWidths[0]) . " | " .
         str_repeat("-", $colWidths[1]) . " | " .
         str_repeat("-", $colWidths[2]) . " | " .
         str_repeat("-", $colWidths[3]) . " |\n";

    foreach ($tableData as $row) {
        echo '| ' . str_pad($row[0], $colWidths[0]) . " | " .
             str_pad($row[1], $colWidths[1]) . " | " .
             str_pad($row[2], $colWidths[2]) . " | " .
             str_pad($row[3], $colWidths[3]) . " |\n";
    }

    echo "\n";
}

// for each bucket, calculate the average time for sparse and full hash
echo "Aggregated results\n";
echo '| ' . str_pad("Bucket", 20) . " | " .
     str_pad("SparseHash", 20) . " | " .
     str_pad("Native", 20) . " | " .
     str_pad("Performance Gain", 20) . " |\n";

foreach ($aggregates as $label => $data) {
    $sparseAvg = array_sum($data['sparse']) / count($data['sparse']);
    $fullAvg = array_sum($data['full']) / count($data['full']);
    $gain = ($sparseAvg > 0) ? ($fullAvg / $sparseAvg) : 0;
    $percentage = round(($gain - 1) * 100, 2);
    $gainStr = sprintf("%.2fx (%+.2f%%)", $gain, $percentage);

    echo '|' . str_pad($label, 20) . " | " .
         str_pad(sprintf("%.4fms", $sparseAvg), 20) . " | " .
         str_pad(sprintf("%.4fms", $fullAvg), 20) . " | " .
         str_pad($gainStr, 20) . " |\n";
}

echo "Benchmark Complete.\n";
