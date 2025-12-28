#!/usr/bin/env php
<?php

/**
 * Hasher Analyzer
 *
 * Meant to be used as a standalone script, run by the root user.
 * For optimal showcasing of the Sparse Hash strategy, this script must be run against a large number of files,
 * ideally in the millions, and ideally with a lot of variance in file sizes and types.
 *
 * This script performs a parallel hashing of all readable files from the system's root directory.
 * It uses child processes to compute both a "sparse" and a "full" hash, stores the results in an SQLite
 * database, and adjusts concurrency dynamically in an attempt to achieve optimal throughput.
 */

////////////////////////////////////////////////////////////////////////////////
//                         CONSTANTS & INITIAL SETUP                          //
////////////////////////////////////////////////////////////////////////////////

define('SCRIPT', realpath($argv[0]));
define('DS', DIRECTORY_SEPARATOR);
define('TD', rtrim(__DIR__, DS) . DS);
define('SRC', realpath(TD . '..' . DS) . DS . 'src' . DS);
define('DB_FILE', TD . 'analyzer.sqlite');

// Manually load the SparseDigest classes
require_once SRC . 'HashResult.php';
require_once SRC . 'File.php';
require_once SRC . 'HashingStrategy.php';
require_once SRC . 'SparseHasher.php';
require_once SRC . 'FileHasher.php';
require_once SRC . 'Strategy/AbstractStrategy.php';
require_once SRC . 'Strategy/SparseStrategy.php';
require_once SRC . 'Strategy/FullStrategy.php';

/**
 * Parse CLI options.
 * Child "Hashing Mode" is indicated by the presence of the "file" parameter
 *
 * @var array
 */
$options = parseCliArgs($argv, [
    'dirs' => ['d', 'dir', 'array'],
    'file' => ['f', 'file', 'string'],
    'algorithm' => ['a', 'algorithm', 'string'],
    'strategy' => ['t', 'strategy', 'string'],
    'stats' => ['s', 'stats', 'bool'],
]);

////////////////////////////////////////////////////////////////////////////////
//                              CHILD (worker)                                //
////////////////////////////////////////////////////////////////////////////////
if ($options['file']) {
    handleChildProcess($options);
    exit(0);
}

////////////////////////////////////////////////////////////////////////////////
//                              MAIN (hashing)                                //
////////////////////////////////////////////////////////////////////////////////
if (!$options['stats']) {
    // can't do anything without the SQLite3 extension
    if (!extension_loaded('sqlite3')) {
        output('error', 'The SQLite3 extension is required.');
        exit(1);
    }

    main($options);
    exit(0);
}

////////////////////////////////////////////////////////////////////////////////
//                              STATISTICS                                    //
////////////////////////////////////////////////////////////////////////////////
if ($options['stats']) {
    // can't do anything without the SQLite3 extension
    if (!extension_loaded('sqlite3')) {
        output('error', 'The SQLite3 extension is required.');
        exit(1);
    }

    if (!is_file(DB_FILE)) {
        output('error', 'The database file does not exist. Run this script without --stats first.');
        usage();
        exit(1);
    }

    // try and open the database
    try {
        $pdo = openDatabase();
    } catch (PDOException $e) {
        output('error', 'Failed to open the database: ' . $e->getMessage());
        exit(1);
    }

    /**
     * Buckets for file sizes (in bytes).
     * [label, minBytes, maxBytes], where maxBytes=null means "unbounded".
     */
    $buckets = [
        ['0-20KB',       0,        20_000],
        ['20-50KB',      20_000,   50_000],
        ['50-100KB',     50_000,   100_000],
        ['100-500KB',    100_000,  500_000],
        ['500KB-1MB',    500_000,  1_000_000],
        ['1MB-5MB',      1_000_000,5_000_000],
        ['5MB-20MB',     5_000_000,20_000_000],
        ['20MB-50MB',    20_000_000,50_000_000],
        ['50MB-200MB',   50_000_000,200_000_000],
        ['200MB-500MB',  200_000_000,500_000_000],
        ['500MB-1GB',    500_000_000,1_000_000_000],
        ['1GB+',         1_000_000_000, null],
    ];

    // Run the statistics script
    stats($buckets);
    exit(0);
}

/**
 * The main function orchestrates the hashing process in two separate passes,
 * removing the duplicated logic for "sparse" and "full" hashes.
 *
 * @param array $options CLI options
 */
function main(array $options): void
{
    ensureRoot();
    output('info', 'Starting Filesystem Analysis...');

    $machine = [];
    exec('grep "model name" /proc/cpuinfo | head -n 1 | awk -F: \'{print $2}\' | xargs', $machine);
    $cores = [];
    exec('lscpu | grep "Core(s) per socket" | awk -F: \'{print $2}\' | xargs', $cores);
    output('debug', "CPU: {$machine[0]}, {$cores[0]} cores");

    output('debug', 'Enumerating all files in selected directories...');

    // Initialize DB
    $pdo = openDatabase();

    // Load or retrieve the list of files from the file system
    if (!is_file('analyzer.files')) {
        $files = enumerateFiles($options['dirs']);
        file_put_contents('analyzer.files', serialize($files));
    } else {
        $files = unserialize(file_get_contents('analyzer.files'));
    }

    runHashPass($pdo, $files, 'sparse', 'Sparse-Hashing (pass 1)');
    output('blank', '', 0);
    runHashPass($pdo, $files, 'full', 'Full-Hashing (pass 2)');
    output('blank', '', 0);
    output('success', 'Filesystem Analysis Completed.');
    output('info', 'Hint: Run the script with --stats to parse the statistics about the current digested dataset.');
}

/**
 * Runs a single hashing pass (sparse or full) with concurrency control and periodic database persistence.
 *
 * @param PDO    $pdo
 * @param array  $files
 * @param string $strategy   Either 'sparse' or 'full'
 * @param string $passName   Label for logs/progress
 */
function runHashPass(PDO $pdo, array $files, string $strategy, string $passName): void
{
    $total = count($files);
    $records = [];
    $index = $done = $doneCached = 0;

    // Concurrency management
    $concurrency = 5;
    $maxConcurrency = 500;
    $adjustmentStep = 10;

    // Additional concurrency-control tracking
    $dontRaiseConcurrencyCount = false;
    $speculativeTrialActive = false;
    $speculativeOldConcurrency = null;
    $speculativeOldBestItemsPerSecond = null;
    $speculativeTrialLastAttemptTime = microtime(true);
    $speculativeTrialInterval = 30; // seconds
    $bestItemsPerSecond = 0;
    $maxThroughputReachedCount = 0;

    $start = microtime(true);

    output('info', "Starting parallel {$passName}...");

    while ($index < $total) {
        adjustConcurrency(
            $concurrency,
            $done,
            $doneCached,
            $start,
            $maxConcurrency,
            $adjustmentStep,
            $dontRaiseConcurrencyCount,
            $speculativeTrialActive,
            $speculativeOldConcurrency,
            $speculativeOldBestItemsPerSecond,
            $speculativeTrialLastAttemptTime,
            $speculativeTrialInterval,
            $bestItemsPerSecond,
            $maxThroughputReachedCount
        );

        $chunkSize = min($concurrency, $total - $index);
        $batch = array_slice($files, $index, $chunkSize);

        // Spawn children
        $pids = [];
        foreach ($batch as $file) {
            // skip if already hashed
            if (avoidRehashing($pdo, $file, $strategy)) {
                $done++;
                $doneCached++;
                continue;
            }

            [$pid, $sockFile] = spawnHashChildProcess(['file' => $file, 'algorithm' => 'xxh128', 'strategy' => $strategy]);
            $pids[$pid] = $sockFile;
        }

        // Collect children results
        foreach ($pids as $pid => $sockFile) {
            // Wait for the child process to end
            while (posix_kill($pid, 0)) {
                usleep(100000);
            }

            $outputData = @file_get_contents($sockFile);
            @unlink($sockFile);
            $done++;

            if (!$outputData) {
                output('error', "Failed to get output from process {$pid}.", 2);
                continue;
            }

            $result = @unserialize($outputData);
            if (!$result) {
                continue;
            }

            if ($result['success']) {
                $records[] = $result;
            }

            // don't report errors, as they are mainly due to file access issues, or empty files.
        }

        // Update console progress
        progress($passName, $start, $done, $total, $concurrency);

        // Periodically flush records to DB
        if (count($records) >= 100) {
            persistRecords($pdo, $strategy, $records);
            $records = [];
        }

        $index += $chunkSize;
    }

    // Flush remaining records
    if (count($records) > 0) {
        persistRecords($pdo, $strategy, $records);
    }
}

/**
 * Statistics script logic
 */
function stats(array $buckets): void
{
    // Retrieve Divergent Hashes
    $divergences = fetchDivergentHashes();

    // Output divergences
    if ($divergences) {
        // group by type
        $grouped = [];
        foreach ($divergences as $div) {
            $grouped[$div['type']][] = $div;
        }

        foreach ($grouped as $type => $divs) {
            output('warning', "Divergent hashes detected for [{$type}]:");
            output('blank', '', 0);
            foreach ($divs as $div) {
                output('warning', "Hash: {$div['hash']}, total: {$div['total']}, distinct: {$div['distinct']}");
                foreach ($div['files'] as $file) {
                    $size = bytesToSize($file['size']);
                    output('warning', "File: {$file['file']} ({$size}) - {$file['mime_type']}", 2);
                }
            }
        }

    } else {
        output('success', 'Yay! No divergent hashes detected. This means that no hash divergences on unique has collisions were found.');
    }

    // Retrieve data
    $rows = fetchAllFiles();

    // Group by bucket
    $grouped = [];
    foreach ($buckets as [$label]) {
        $grouped[$label] = [
            'sizeCount' => 0,
            'sparseTimes' => [],
            'fullTimes' => [],
        ];
    }

    foreach ($rows as $row) {
        $label = getSizeBucketLabel((int)$row['size'], $buckets);
        if (!isset($grouped[$label])) {
            // Possibly outside the known buckets or ???
            continue;
        }
        $grouped[$label]['sizeCount']++;
        
        // Only include valid (positive) time values
        $sparseTime = (float)$row['sparse_hash_compute_time'];
        $fullTime = (float)$row['full_hash_compute_time'];
        
        if ($sparseTime > 0) {
            $grouped[$label]['sparseTimes'][] = $sparseTime;
        }
        if ($fullTime > 0) {
            $grouped[$label]['fullTimes'][] = $fullTime;
        }
    }

    // Output
    output('stat', 'Hash Statistics Overview');
    output('blank', str_repeat('─', 80), 0);

    foreach ($grouped as $label => $data) {
        $count = $data['sizeCount'];
        if ($count === 0) {
            output('warning', "Bucket [{$label}] has no files.");
            continue;
        }

        $sparseValidCount = count($data['sparseTimes']);
        $fullValidCount = count($data['fullTimes']);
        
        $sparseStats = computeStats($data['sparseTimes']);
        $fullStats = computeStats($data['fullTimes']);

        // "Mean" is the same as "average" in the user's request
        $meanSparse = $sparseStats['mean'];
        $meanFull = $fullStats['mean'];

        $medianSparse = $sparseStats['median'];
        $medianFull = $fullStats['median'];

        $rangeSparse = $sparseStats['range'];
        $rangeFull = $fullStats['range'];

        $stdDevSparse = $sparseStats['stddev'];
        $stdDevFull = $fullStats['stddev'];

        // Performance gain: ratio of full-hash time to sparse-hash time
        // e.g. if full=10s, sparse=5s -> ratio=2x (sparse is 2x faster)
        $ratio = ($meanSparse > 0) ? ($meanFull / $meanSparse) : 0.0;

        output('blank', "Bucket: [{$label}] — {$count} files total");
        
        // Sparse Hash stats
        if ($sparseValidCount > 0) {
            output('info', sprintf(
                "SparseHash (%d samples): mean=%.2fms, median=%.2fms, stddev=%.2fms",
                $sparseValidCount,
                $meanSparse,
                $medianSparse,
                $stdDevSparse
            ), 2);
            output('blank', "Range: {$rangeSparse}", 3);
        } else {
            output('warning', "SparseHash: No valid time samples (all 0 or negative)", 2);
        }
        
        // Full Hash stats
        if ($fullValidCount > 0) {
            output('info', sprintf(
                "FullHash (%d samples): mean=%.2fms, median=%.2fms, stddev=%.2fms",
                $fullValidCount,
                $meanFull,
                $medianFull,
                $stdDevFull
            ), 2);
            output('blank', "Range: {$rangeFull}", 3);
        } else {
            output('warning', "FullHash: No valid time samples (all 0 or negative)", 2);
        }

        // Performance comparison
        if ($sparseValidCount > 0 && $fullValidCount > 0) {
            output('blank', '', 0);
            if ($ratio > 1.1) {
                output('better', sprintf("SparseHash is ~%.1fx faster than FullHash", $ratio), 2);
            } elseif ($ratio >= 0.9) {
                output('info', sprintf("Similar performance (ratio: %.2fx)", $ratio), 2);
            } else {
                output('worse', sprintf("FullHash is faster (ratio: %.2fx)", $ratio), 2);
            }
            
            // Render comparison bar
            echo renderBar($meanSparse, $meanFull) . "\n";
        }
        
        output('blank', str_repeat('─', 80), 0);
    }
}

////////////////////////////////////////////////////////////////////////////////
//                               CHILD PROCESS                                //
////////////////////////////////////////////////////////////////////////////////

/**
 * Handles child process logic: compute and serialize hash results.
 * This will be called twice, for each strategy "sparse" and "full", to avoid NVMe disk cache effects on hashing times.
 *
 * @param array $options CLI options with 'file' (required) and 'algorithm' (optional).
 */
function handleChildProcess(array $options): void
{
    // register a custom shutdown function to catch any unhanded error
    register_shutdown_function(function () use ($options) {
        $error = error_get_last();
        if ($error) {
            switch ($error['type']) {
                case E_ERROR:
                    $type = 'Error';
                    break;
                case E_PARSE:
                    $type = 'Parse Error';
                    break;
                case E_CORE_ERROR:
                    $type = 'Core Error';
                    break;
                case E_COMPILE_ERROR:
                    $type = 'Compile Error';
                    break;
                default:
                    $type = 'Unknown Error';
            }

            echo serialize([
                'success' => false,
                'file' => $options['file'],
                'message' => "{$type}: {$error['message']} in {$error['file']} on line {$error['line']}",
            ]);
        }
    });

    try {
        $algorithm = (!empty($options['algorithm']))
            ? $options['algorithm']
            : 'xxh128';
        $strategy = (!empty($options['strategy']))
            ? $options['strategy']
            : 'sparse';

        $file = \Mfonte\SparseDigest\File::new($options['file']);
        $sparseHasher = \Mfonte\SparseDigest\SparseHasher::new($options['file']);
        $fileHasher = \Mfonte\SparseDigest\FileHasher::new($options['file']);

        switch ($strategy) {
            case 'sparse':
                $hash = $sparseHasher->hashAs($algorithm);
                $time = $sparseHasher->getComputeTime();
                break;
            case 'full':
                $hash = $fileHasher->hashAs($algorithm);
                $time = $fileHasher->getComputeTime();
                break;
            default:
                throw new \InvalidArgumentException("Invalid strategy: {$strategy}");
        }

        $size = $file->getSize();
        $mime_type = $file->getMimeType();
        unset($sparseHasher, $fileHasher, $file);

        echo serialize([
            'success' => true,
            'file' => $options['file'],
            'strategy' => $strategy,
            'algorithm' => $algorithm,
            'hash' => $hash,
            'time' => $time,
            'size' => $size,
            'mime_type' => $mime_type,
        ]);
    } catch (\Exception $e) {
        echo serialize([
            'success' => false,
            'file' => $options['file'],
            'message' => $e->getMessage(),
        ]);
    }
}

////////////////////////////////////////////////////////////////////////////////
//                               DATABASE / I/O                               //
////////////////////////////////////////////////////////////////////////////////

/**
 * Opens or creates the SQLite database and returns the PDO instance.
 *
 * @return \PDO
 */
function openDatabase(): PDO
{
    try {
        $pdo = new PDO("sqlite:" . DB_FILE);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Create the "files" table if it doesn't exist
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS files (
                file TEXT PRIMARY KEY,
                sparse_hash TEXT DEFAULT NULL,
                sparse_hash_compute_time REAL DEFAULT NULL,
                full_hash TEXT DEFAULT NULL,
                full_hash_compute_time REAL DEFAULT NULL,
                size INTEGER DEFAULT NULL,
                mime_type TEXT DEFAULT NULL
            );
        ");

        // Create indexes if they don't exist.
        $pdo->exec("CREATE UNIQUE INDEX IF NOT EXISTS idx_files_file ON files (file);");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_files_sparse_hash ON files (sparse_hash);");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_files_full_hash ON files (full_hash);");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_files_size ON files (size);");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_files_mime_type ON files (mime_type);");

        return $pdo;
    } catch (\PDOException $e) {
        throw new \PDOException("Failed to open the database or create the table/indexes: " . $e->getMessage());
    }
}

/**
 * Saves an array of hashed file records to the SQLite database.
 * Each record must contain keys: file, hash, time, size, mime_type.
 *
 * @param \PDO   $db       Database connection
 * @param string $strategy Hashing strategy ('sparse' or 'full')
 * @param array  $records  Hash result records
 *
 * @throws \RuntimeException on failure.
 */
function persistRecords(PDO $db, string $strategy, array $records): void
{
    $query = '';
    try {
        if ($strategy === 'sparse') {
            $cols = ['file', 'sparse_hash', 'sparse_hash_compute_time', 'size', 'mime_type'];
            $insert = "INSERT INTO `files` (`file`, `sparse_hash`, `sparse_hash_compute_time`, `size`, `mime_type`) VALUES ";
            $onDupe = "ON CONFLICT(`file`) DO UPDATE SET `sparse_hash`=excluded.`sparse_hash`, `sparse_hash_compute_time`=excluded.`sparse_hash_compute_time`";
        } elseif ($strategy === 'full') {
            $cols = ['file', 'full_hash', 'full_hash_compute_time', 'size', 'mime_type'];
            $insert = "INSERT INTO `files` (`file`, `full_hash`, `full_hash_compute_time`, `size`, `mime_type`) VALUES ";
            $onDupe = "ON CONFLICT(`file`) DO UPDATE SET `full_hash`=excluded.`full_hash`, `full_hash_compute_time`=excluded.`full_hash_compute_time`";
        } else {
            throw new \InvalidArgumentException("Invalid strategy: {$strategy}");
        }

        $places = '(' . implode(', ', array_fill(0, count($cols), '?')) . ')';
        $data = array_reduce($records, function ($carry, $record) {
            $carry[] = $record['file'];
            // HashResult object must be converted to hex string
            $hash = $record['hash'];
            $carry[] = ($hash instanceof \Mfonte\SparseDigest\HashResult) ? $hash->toHex() : (string)$hash;
            $carry[] = $record['time'];
            $carry[] = $record['size'];
            $carry[] = $record['mime_type'];
            return $carry;
        }, []);

        $db->beginTransaction();
        $query = $insert . implode(', ', array_fill(0, count($records), $places)) . " $onDupe";
        $stmt = $db->prepare($query);
        $stmt->execute($data);
        $db->commit();
    } catch (PDOException $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw new RuntimeException("SQLite error: {$e->getMessage()} (Query: {$query})");
    }
}

////////////////////////////////////////////////////////////////////////////////
//                               HELPER METHODS                               //
////////////////////////////////////////////////////////////////////////////////

/**
 * Spawns a child process of this script using the provided arguments.
 *
 * @param array $arguments Key-value array of arguments to pass to the process (e.g. ['file' => '/path']).
 *
 * @return array [PID, TempFileUsedForOutput]
 */
function spawnHashChildProcess(array $arguments): array
{
    $argString = '';
    foreach ($arguments as $key => $value) {
        $key = trim($key, '-');
        if ($key && strlen($value)) {
            $value = escapeshellarg($value);
            $argString .= "--{$key} {$value} ";
        } elseif ($key) {
            $argString .= "--{$key} ";
        } elseif ($value) {
            $value = escapeshellarg($value);
            $argString .= "{$value} ";
        }
    }
    $argString = trim($argString);

    $phpExec = "/usr/bin/php -d memory_limit=-1";
    $sockFile = tempnam(sys_get_temp_dir(), 'sparsedigest_process_');
    $command = sprintf(
        "%s %s %s > %s 2>&1 & echo $!",
        $phpExec,
        SCRIPT,
        $argString,
        $sockFile
    );

    $pid = (int)exec($command);
    return [$pid, $sockFile];
}

/**
 * Simple function to convert bytes into a human-readable size string.
 *
 * @param int $size The number of bytes
 *
 * @return string e.g., '12.34 MB'
 */
function bytesToSize(int $size): string
{
    $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB'];
    $unit = 0;
    while ($size >= 1024 && $unit < count($units) - 1) {
        $size /= 1024;
        $unit++;
    }
    return round($size, 2) . ' ' . $units[$unit];
}

/**
 * Recursively scans the system's root directory for all readable files.
 * Returns an array of absolute file paths.
 *
 * @return array
 */
function enumerateFiles(array $roots): array
{
    // ensure all members of $roots are directories
    $roots = array_map(function ($root) {
        if (!is_dir($root)) {
            throw new \InvalidArgumentException("Invalid directory provided: {$root}");
        }
        return rtrim($root, DS);
    }, $roots);

    $files = [];
    $excludedDirs = ['/boot', '/dev', '/lost+found', '/proc', '/run', '/sys', '/tmp', '/mnt/wslg'];

    $directories = array_unique(array_reduce($roots, function ($carry, $dir) {
        $directories = glob("{$dir}/*", GLOB_ONLYDIR);
        if ($directories === false) {
            $directories = [];
        }

        return array_merge($carry, $directories);
    }, []));

    foreach ($directories as $dir) {
        if (in_array($dir, $excludedDirs, true)) {
            continue;
        }

        output('debug', "Enumerating files in {$dir}...", 2);

        try {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::SELF_FIRST
            );

            foreach ($iterator as $file) {
                if ($file->isFile() && $file->isReadable()) {
                    $files[] = $file->getPathname();
                }
            }
        } catch (Exception $e) {
            // no-op
        }
    }

    return $files;
}

/**
 * Writes a formatted message to either STDOUT or STDERR based on $level.
 *
 * @param string $level   One of 'blank', 'debug', 'success', 'error', 'warning', 'info', 'stat', 'better', 'worse'
 * @param string $message The message to display
 * @param int    $indent  Number of spaces to prepend
 *
 * @throws \RuntimeException if the message cannot be written
 */
function output(string $level, string $message, int $indent = 1): void
{
    $map = [
        'blank'   => '',
        'debug'   => '💻',
        'success' => '✅',
        'error'   => '⛔',
        'warning' => '⚠️',
        'info'    => '💡',
        'stat'    => '📊',
        'better'  => '📈',
        'worse'   => '📉',
    ];
    if (!isset($map[$level])) {
        $level = 'info';
    }

    $icon = str_pad($map[$level], 2, ' ', STR_PAD_BOTH);
    $padding = str_repeat(' ', $indent);
    $output = $padding . $icon . ' ' . $message . PHP_EOL;

    $stream = ($level === 'error') ? STDERR : STDOUT;
    $written = fwrite($stream, $output);
    if ($written === false) {
        throw new \RuntimeException("Could not write to stream");
    }
}

/**
 * Writes a progress message to STDOUT (inline) without appending a newline,
 * to be overwritten by subsequent calls, giving a dynamic progress bar effect.
 *
 * @param string $task
 * @param float  $start
 * @param int    $current
 * @param int    $total
 * @param int    $concurrency
 */
function progress(string $task, float $start, int $current, int $total, int $concurrency): void
{
    $now = microtime(true);
    $mem = bytesToSize(memory_get_usage());
    $percent = ($current / $total) * 100;
    $perSecond = round($current / ($now - $start), 2);

    $output = sprintf(
        "\033[2K\r 🕑 %s: %d/%d items done (%d%%) | %.2f items/s (%d FRK) | %s MEM",
        $task,
        $current,
        $total,
        $percent,
        $perSecond,
        $concurrency,
        $mem
    );

    fwrite(STDOUT, $output);
}

/**
 * Ensures this script is running as root user, or exits with an error.
 */
function ensureRoot(): void
{
    if (function_exists('posix_geteuid') && posix_geteuid() !== 0) {
        output('error', 'This script must be run as root.');
        usage();
        exit(1);
    }
}

/**
 * Displays the usage information for the analyzer script.
 *
 * This function outputs the usage instructions and available options for the
 * analyzer script. It provides details on how to use the script, including
 * the available command-line options and their descriptions.
 *
 * @return void
 */
function usage(): void
{
    output('blank', 'Usage:', 0);
    output('blank', 'sudo php analyzer.php [-d|--dir <dir>] [-s|--stats] [-t|--strategy <strategy>] [-a|--algorithm <algorithm>] [-f|--file <file>]', 1);
    output('blank', 'Options:', 0);
    output('blank', '-d, --dir <dir>              Directory to scan for files (can be used multiple times)', 1);
    output('blank', '-s, --stats                  Parse statistics about the current digested dataset', 1);
    output('blank', '-f, --file <file>            Hashing mode: File to hash', 1);
    output('blank', '-a, --algorithm <algorithm>  Hashing mode: Algorithm for hashing (default: "xxh128")', 1);
    output('blank', '-t, --strategy <strategy>    Hashing mode: Strategy, either "sparse" or "full" (default: "sparse")', 1);
}

/**
 * Parses command-line arguments based on a given configuration.
 *
 * @param array $argv The command-line arguments, typically from $argv.
 * @param array $config The configuration array where each key is an argument name and the value is an array with three elements:
 *                      - short option (single character)
 *                      - long option (string)
 *                      - type (string, one of 'array', 'bool', 'string', 'int')
 *
 * @return array An associative array of parsed arguments with default values initialized based on the type.
 *
 * @throws \InvalidArgumentException If the configuration for any key is invalid or if an invalid type is specified.
 */
function parseCliArgs(array $argv, array $config): array
{
    $args = array_slice($argv, 1); // Remove script name
    $parsed = [];

    // Initialize default values based on type
    foreach ($config as $key => $val) {
        if (count($val) !== 3) {
            // Invalid config
            throw new \InvalidArgumentException("Invalid config for key: {$key}");
        }

        list($short, $long, $type) = $val;

        switch ($type) {
            case 'array':
                $parsed[$key] = [];
                break;
            case 'bool':
                $parsed[$key] = false;
                break;
            case 'string':
            case 'int':
                $parsed[$key] = null;
                break;
            default:
                // Invalid type
                throw new \InvalidArgumentException("Invalid type for key: {$key}");
        }
    }

    for ($i = 0; $i < count($args); $i++) {
        $arg = $args[$i];

        foreach ($config as $key => $val) {
            list($short, $long, $type) = $val;

            if ($arg === "-{$short}" || $arg === "--{$long}") {
                switch ($type) {
                    case 'array':
                        while (isset($args[$i + 1]) && !str_starts_with($args[$i + 1], '-')) {
                            $parsed[$key][] = $args[++$i];
                        }
                        break;
                    case 'bool':
                        $parsed[$key] = true;
                        break;
                    case 'string':
                    case 'int':
                        if (isset($args[$i + 1]) && !str_starts_with($args[$i + 1], '-')) {
                            $parsed[$key] = ($type === 'int') ? (int) $args[++$i] : $args[++$i];
                        }
                        break;
                }
                break; // Move to next argument
            }
        }
    }

    return $parsed;
}

////////////////////////////////////////////////////////////////////////////////
//                          STATISTICS HELPERS                                //
////////////////////////////////////////////////////////////////////////////////

/**
 * Simple helper to categorize a file-size into our buckets.
 */
function getSizeBucketLabel(int $size, array $buckets): string
{
    foreach ($buckets as [$label, $min, $max]) {
        if ($max === null) {
            if ($size >= $min) {
                return $label;
            }
        } else {
            if ($size >= $min && $size < $max) {
                return $label;
            }
        }
    }
    return '???';
}

/**
 * Returns the mean, median, range, and standard deviation of an array of values.
 *
 * @param array<float> $values Array of time values in milliseconds
 * @return array{mean: float, median: float, range: string, stddev: float, min: float, max: float, count: int}
 */
function computeStats(array $values): array
{
    if (!$values) {
        return ['mean' => 0.0, 'median' => 0.0, 'range' => 'N/A', 'stddev' => 0.0, 'min' => 0.0, 'max' => 0.0, 'count' => 0];
    }

    sort($values);
    $count = count($values);

    // Mean
    $mean = array_sum($values) / $count;

    // Median
    $mid = (int) floor($count / 2);
    if ($count % 2) {
        $median = $values[$mid];
    } else {
        $median = ($values[$mid - 1] + $values[$mid]) / 2;
    }

    // Min/Max
    $min = $values[0];
    $max = $values[$count - 1];

    // Range - formatted nicely
    $range = sprintf("%.2fms — %.2fms", $min, $max);

    // Standard deviation
    $variance = array_reduce($values, function ($carry, $value) use ($mean) {
        return $carry + ($value - $mean) ** 2;
    }, 0) / $count;
    $stddev = sqrt($variance);

    return ['mean' => $mean, 'median' => $median, 'range' => $range, 'stddev' => $stddev, 'min' => $min, 'max' => $max, 'count' => $count];
}

/**
 * Renders a horizontal ASCII bar to compare two values.
 *
 * @param float $sparse Sparse hash time in ms
 * @param float $full Full hash time in ms
 * @param int $width Bar width in characters
 * @return string Formatted comparison bar
 */
function renderBar(float $sparse, float $full, int $width = 40): string
{
    // Avoid division by zero or invalid data
    if ($sparse <= 0 && $full <= 0) {
        return '   [No valid comparison data]';
    }
    
    $max = max($sparse, $full);
    if ($max <= 0) {
        return '   [No valid comparison data]';
    }

    $sparseLen = ($sparse > 0) ? (int) round(($sparse / $max) * $width) : 0;
    $fullLen = ($full > 0) ? (int) round(($full / $max) * $width) : 0;
    
    // Ensure at least 1 character if value > 0
    if ($sparse > 0 && $sparseLen === 0) {
        $sparseLen = 1;
    }
    if ($full > 0 && $fullLen === 0) {
        $fullLen = 1;
    }

    // Green for sparse (faster), red for full
    $sparseBar = str_repeat("\033[42m \033[0m", $sparseLen);
    $fullBar = str_repeat("\033[41m \033[0m", $fullLen);
    
    // Pad to consistent width
    $sparsePad = str_repeat(' ', $width - $sparseLen);
    $fullPad = str_repeat(' ', $width - $fullLen);

    return sprintf(
        "   SparseHash: [%s] %.4f ms\n   FullHash: [%s] %.4f ms",
        $sparseBar,
        $sparse,
        $fullBar,
        $full
    );
}

/**
 * Connect to the DB and retrieve all rows.
 */
function fetchAllFiles(): array
{
    $pdo = new PDO('sqlite:' . DB_FILE);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $stmt = $pdo->query("
        SELECT file, size, sparse_hash_compute_time, full_hash_compute_time
        FROM files
        WHERE size IS NOT NULL
        AND sparse_hash_compute_time IS NOT NULL
        AND full_hash_compute_time IS NOT NULL
    ");

    $ret = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $pdo = null;

    return $ret;
}

/**
 * Connect to the DB and execute the divergent hashes query.
 */
function fetchDivergentHashes(): array
{
    $pdo = new PDO('sqlite:' . DB_FILE);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $ret = $pdo->query("
        SELECT 'sparseHash' AS collision_type,
            sparse_hash AS hash,
            GROUP_CONCAT(file || ' - ' || size || ' - ' || mime_type, '; ') AS files,
            COUNT(*) AS total,
            COUNT(DISTINCT full_hash) AS distinct_fullhashes
        FROM files
        WHERE sparse_hash IS NOT NULL AND full_hash IS NOT NULL
        GROUP BY sparse_hash
        HAVING COUNT(*) > 1 AND COUNT(DISTINCT full_hash) > 1

        UNION

        SELECT 'fullHash' AS collision_type,
            full_hash AS hash,
            GROUP_CONCAT(file || ' - ' || size || ' - ' || mime_type, '; ') AS files,
            COUNT(*) AS total,
            COUNT(DISTINCT sparse_hash) AS distinct_sparsehashes
        FROM files
        WHERE sparse_hash IS NOT NULL AND full_hash IS NOT NULL
        GROUP BY full_hash
        HAVING COUNT(*) > 1 AND COUNT(DISTINCT sparse_hash) > 1;
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    // for each divergent hash, obtain raw data
    $records = [];
    foreach ($ret as &$row) {
        $hash = $row['hash'];
        $type = $row['collision_type'];

        if (empty($hash)) {
            // this is a false positive, skip
            continue;
        }
        
        switch ($type) {
            case 'sparseHash':
                $stmt = $pdo->prepare("SELECT * FROM files WHERE sparse_hash = :hash");
                break;
            case 'fullHash':
                $stmt = $pdo->prepare("SELECT * FROM files WHERE full_hash = :hash");
                break;
        }

        $stmt->execute(['hash' => $hash]);
        $files = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $records[] = [
            'hash' => $hash,
            'type' => $type,
            'total' => $row['total'],
            'distinct' => ($type === 'sparseHash') ? $row['distinct_fullhashes'] : $row['distinct_sparsehashes'],
            'files' => $files,
        ];
    }

    return $records;
}

/**
 * Determines if we have already persisted a file hash in the database, for a given strategy.
 *
 * @param PDO    $pdo  Database connection
 * @param string $file File path
 * @param string $strategy Hashing strategy ('sparse' or 'full')
 *
 * @return bool
 */
function avoidRehashing(PDO $pdo, string $file, string $strategy) : bool
{
    $column = ($strategy === 'sparse') ? 'sparse_hash' : 'full_hash';
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM files WHERE file = :file AND {$column} IS NOT NULL");
    $stmt->execute(['file' => $file]);
    return (bool) $stmt->fetchColumn();
}

////////////////////////////////////////////////////////////////////////////////
//                          CONCURRENCY ADJUSTMENT                            //
////////////////////////////////////////////////////////////////////////////////

/**
 * Dynamically adjusts concurrency based on throughput changes,
 * attempting to find an optimal concurrency level.
 *
 * @param int    &$concurrency                      Current concurrency (adjusted in-place).
 * @param int    $done                              Number of files processed so far.
 * @param int    $doneCached                        Number of files that were not effectively processed, but obtained from cache.
 * @param float  $start                             Script start time.
 * @param int    $maxConcurrency                    Maximum concurrency limit.
 * @param int    $adjustmentStep                    Step size for concurrency increments.
 * @param bool   &$dontRaiseConcurrencyCount        Whether concurrency raising is temporarily paused.
 * @param bool   &$speculativeTrialActive           Flag indicating whether we are in a trial run.
 * @param int    &$speculativeOldConcurrency        Previous concurrency value before trial.
 * @param float  &$speculativeOldBestItemsPerSecond Best throughput observed before trial.
 * @param float  &$speculativeTrialLastAttemptTime  When the last trial started, in epoch seconds.
 * @param int    $speculativeTrialInterval          How frequently to attempt a new concurrency trial, in seconds.
 * @param float  &$bestItemsPerSecond               The highest throughput observed so far.
 * @param int    &$maxThroughputReachedCount        How many times we've seen no improvement in throughput.
 *
 * @return void
 */
function adjustConcurrency(
    int &$concurrency,
    int $done,
    int $doneCached,
    float $start,
    int $maxConcurrency,
    int $adjustmentStep,
    bool &$dontRaiseConcurrencyCount,
    bool &$speculativeTrialActive,
    ?int &$speculativeOldConcurrency,
    ?float &$speculativeOldBestItemsPerSecond,
    float &$speculativeTrialLastAttemptTime,
    int $speculativeTrialInterval,
    float &$bestItemsPerSecond,
    int &$maxThroughputReachedCount
): void {
    $elapsed = microtime(true) - $start;
    $itemsPerSecond = ($done > 0) ? ($done - $doneCached) / $elapsed : 0;

    // If we see a new best throughput, reset counters
    if ($itemsPerSecond > $bestItemsPerSecond) {
        $bestItemsPerSecond = $itemsPerSecond;
        $maxThroughputReachedCount = 0;
    } else {
        $maxThroughputReachedCount++;
    }

    // If throughput doesn't improve after repeated checks, stop raising concurrency
    if ($maxThroughputReachedCount > 3) {
        $dontRaiseConcurrencyCount = true;
    }

    // If we are currently in a speculative trial...
    if ($speculativeTrialActive) {
        // Revert if no improvement or proceed if improvement was noticed
        if ($itemsPerSecond <= $speculativeOldBestItemsPerSecond) {
            $concurrency = $speculativeOldConcurrency;
        } else {
            $dontRaiseConcurrencyCount = false;
        }
        $speculativeTrialActive = false;
        $speculativeOldConcurrency = null;
        $speculativeOldBestItemsPerSecond = null;
        $speculativeTrialLastAttemptTime = microtime(true);
        return;
    }

    // If concurrency raises are paused, check if enough time has passed to try again
    if ($dontRaiseConcurrencyCount) {
        if ($concurrency < $maxConcurrency &&
            (microtime(true) - $speculativeTrialLastAttemptTime > $speculativeTrialInterval)) {
            $speculativeTrialActive = true;
            $speculativeOldConcurrency = $concurrency;
            $speculativeOldBestItemsPerSecond = $bestItemsPerSecond;
            $concurrency = min($maxConcurrency, $concurrency + $adjustmentStep);
        }
        return;
    }

    // Otherwise, continue raising concurrency until max is reached
    if ($concurrency < $maxConcurrency) {
        $concurrency = min($maxConcurrency, $concurrency + $adjustmentStep);
    }
}
