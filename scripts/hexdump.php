#!/usr/bin/env php
<?php

/**
 * HexView Script
 *
 * Displays a file's contents in a hexadecimal grid, optionally comparing two files side by side.
 *
 * Usage:
 *   php hexdump.php <file1> [file2]
 *
 * If only <file1> is provided, we print its hex dump.
 * If both <file1> and <file2> are provided, we run a side-by-side comparison, highlighting differing lines.
 *
 * Configuration:
 *   - $chunkSize: how many bytes per row (default 16).
 *   - $visualSplit: insert extra spacing every N bytes (for readability). False to disable.
 *   - $eolString: line terminator (default PHP_EOL).
 *   - $outputFile: if non-empty, write to that file. Otherwise, write to stdout.
 *
 * Note: Large files can produce large outputs. Use caution when hex-dumping huge files.
 */

////////////////////////////////////////////////////////////////////////////////
//                         CONSTANTS & INITIAL SETUP                          //
////////////////////////////////////////////////////////////////////////////////

define('SCRIPT', realpath($argv[0]) ?: __FILE__);
define('DS', DIRECTORY_SEPARATOR);
define('TD', rtrim(__DIR__, DS) . DS);
define('SRC', realpath(TD . '..' . DS) . DS . 'src' . DS);

require_once SRC . 'Digest.php';
require_once SRC . 'Digestable.php';
require_once SRC . 'Implementation.php';
require_once SRC . 'SparseDigest.php';
require_once SRC . 'Hasher/Base.php';
require_once SRC . 'Hasher/Sparse.php';

// Default Configuration
$chunkSize   = 16;
$visualSplit = 8;       // Insert extra spacing every 8 bytes (if < chunkSize)
$eolString   = PHP_EOL;
$outputFile  = '';      // 'php://output' by default if blank

////////////////////////////////////////////////////////////////////////////////
//                                  MAIN                                      //
////////////////////////////////////////////////////////////////////////////////

main($argv);

/**
 * Main driver function:
 *  1) Parses CLI arguments for up to two file paths.
 *  2) If only one file given, hex-dump that file.
 *  3) If two files given, do a line-by-line compare of the hex dump, highlighting differences.
 *
 * @param array $argv Command-line arguments.
 * @return void
 */
function main(array $argv): void
{
    $file1 = $argv[1] ?? null;
    $file2 = $argv[2] ?? null;

    if (!$file1 || !is_readable($file1)) {
        echo "Usage: php " . basename(SCRIPT) . " <file1> [file2]\n";
        exit(1);
    }

    if ($file2 !== null) {
        // Compare mode
        if (!file_exists($file2) || !is_readable($file2)) {
            die("Cannot read second file: '{$file2}'\n");
        }

        $d1 = \Mfonte\SparseDigest\File::new($file1);
        $sd1 = \Mfonte\SparseDigest\SparseHasher::new($file1);
        $d2 = \Mfonte\SparseDigest\File::new($file2);
        $sd2 = \Mfonte\SparseDigest\SparseHasher::new($file2);

        $t1 = microtime(true);
        $f1MimeType = $d1->getMimeType();
        $t2 = microtime(true);
        $f1MimeTimeDetectionMs = round(($t2 - $t1) * 1000, 2);

        $t1 = microtime(true);
        $f2MimeType = $d2->getMimeType();
        $t2 = microtime(true);
        $f2MimeTimeDetectionMs = round(($t2 - $t1) * 1000, 2);

        $f1Size = $d1->getSize();
        $f1Offsets = computeSampleOffsets($f1Size);
        $f1Start = microtime(true);
        $f1Hash = $sd1->hash();
        $f1Time = round((microtime(true) - $f1Start) * 1000, 4);
        echo "File 1: {$file1} - Size: {$f1Size} bytes\n";
        echo "File 1 Can be Sampled: " . ($d1->cantBeSampled() ? 'No' : 'Yes') . "\n";
        echo "File 1 Mime Type: {$f1MimeType} ({$f1MimeTimeDetectionMs} ms)\n";
        echo "File 1 Sample Offsets: " . implode(', ', $f1Offsets) . "\n";
        echo "File 1 Hash: {$f1Hash} ({$f1Time} ms)\n";
        
        $f2Size = $d2->getSize();
        $f2Offsets = computeSampleOffsets($f2Size);
        $f2Start = microtime(true);
        $f2Hash = $sd2->hash();
        $f2Time = round((microtime(true) - $f2Start) * 1000, 4);
        echo "File 2: {$file2} - Size: {$f2Size} bytes\n";
        echo "File 2 Can be Sampled: " . ($d2->cantBeSampled() ? 'No' : 'Yes') . "\n";
        echo "File 2 Mime Type: {$f2MimeType} ({$f2MimeTimeDetectionMs} ms)\n";
        echo "File 2 Sample Offsets: " . implode(', ', $f2Offsets) . "\n";
        echo "File 2 Hash: {$f2Hash} ({$f2Time} ms)\n";

        $lines1 = generateHexDump($file1);
        $lines2 = generateHexDump($file2);

        compareHexDumps($lines1, $lines2);
    } else {
        // Single-file mode
        $f = \Mfonte\SparseDigest\File::new($file1);
        $fSize = $f->getSize();
        $fOffsets = computeSampleOffsets($fSize);
        echo "File: {$file1} - Size: {$fSize} bytes\n";
        echo "File Can be Sampled: " . ($f->cantBeSampled() ? 'No' : 'Yes') . "\n";
        echo "File Sample Offsets: " . implode(', ', $fOffsets) . "\n";
        echo "Mime Type: " . $f->getMimeType() . "\n";

        $lines = generateHexDump($file1);
        foreach ($lines as $line) {
            echo $line . PHP_EOL;
        }
    }
}

////////////////////////////////////////////////////////////////////////////////
//                       HEX GENERATION & COMPARISON                          //
////////////////////////////////////////////////////////////////////////////////

/**
 * Generates an array of hex-dump lines (including header and row data) for a given file.
 *
 * @param string $filePath File to hex-dump.
 * @return string[] Array of lines (without trailing newline).
 */
function generateHexDump(string $filePath): array
{
    global $chunkSize, $visualSplit, $eolString, $outputFile;

    if (!is_readable($filePath)) {
        throw new RuntimeException("Cannot read source file: {$filePath}");
    }
    $sourceSize = filesize($filePath);
    if ($sourceSize < 1) {
        return ["File '{$filePath}' is empty."];
    }

    // Decide on left padding length for hex addresses
    $leftPad = strlen(dechex((int)($sourceSize / $chunkSize))) + 1;
    if ($visualSplit >= $chunkSize) {
        $visualSplit = false;
    }
    if ($chunkSize < 1) {
        $chunkSize = 16;
    }

    $handle = fopen($filePath, 'rb');
    if (!$handle) {
        throw new RuntimeException("Cannot open file: {$filePath}");
    }

    // Build header lines
    $headerLines = buildHeader($chunkSize, $visualSplit, $leftPad);
    $outputLines = [$headerLines[0], $headerLines[1]];

    $maxRowLen  = 0;
    $rawView    = '';
    $filePos    = 0;

    while ($filePos < $sourceSize) {
        // only keep the first and last 4KB of the file
        if ($filePos > 4096 && $filePos < ($sourceSize - 4096)) {
            $filePos += $chunkSize;
            continue;
        }

        $chunk = fread($handle, $chunkSize);
        if ($chunk === false) {
            break;
        }

        //$rowAddr = str_pad(dechex($filePos), $leftPad, '0', STR_PAD_LEFT) . ' | ';
        $rowAddr = str_pad($filePos, $leftPad, ' ', STR_PAD_LEFT) . ' | ';

        $thisChunk    = str_split($chunk, 1);
        $innerCounter = 0;
        $rowHex       = '';
        $rowAscii     = '';

        foreach ($thisChunk as $char) {
            // Hex
            $rowHex .= str_pad(strtoupper(dechex(ord($char))), 2, '0', STR_PAD_LEFT) . ' ';

            // Ascii
            $rowAscii .= ctype_print($char) ? $char : '.';

            // Visual split
            if ($visualSplit !== false) {
                $innerCounter++;
                if (($innerCounter % $visualSplit) === 0) {
                    $rowHex .= '  ';
                    $rowAscii .= ' ';
                }
            }
        }

        // Combine row
        $combinedRow = $rowAddr . $rowHex . $rowAscii;
        $rowLength   = strlen($combinedRow);
        if ($rowLength > $maxRowLen) {
            $maxRowLen = $rowLength;
        }
        $outputLines[] = $combinedRow;

        $filePos += $chunkSize;
    }

    fclose($handle);

    // Pad each row to maxRowLen for alignment
    foreach ($outputLines as $i => $row) {
        if (strlen($row) < $maxRowLen) {
            $outputLines[$i] = str_pad($row, $maxRowLen, ' ');
        }
    }

    return $outputLines;
}

/**
 * Builds the two header lines (ruler) for the hex dump, including offsets for each byte and split spacing.
 *
 * @param int  $chunkSize   Number of bytes per row.
 * @param bool|int $visualSplit Extra spacing every N bytes (false to disable).
 * @param int  $leftPad     Width for the address column.
 * @return string[]         Array with two lines: [header, headerRule].
 */
function buildHeader(int $chunkSize, $visualSplit, int $leftPad): array
{
    // First line: addresses
    $lineAddr = str_repeat(' ', $leftPad) . '   ';
    // Second line: dashed rule
    $lineRule = str_repeat(' ', $leftPad) . '   ';

    for ($i = 0; $i < $chunkSize; $i++) {
        $lineAddr .= str_pad(strtoupper(dechex($i)), 2, '0', STR_PAD_LEFT) . ' ';
        $lineRule .= '-- ';

        if ($visualSplit !== false && ($i + 1) % $visualSplit === 0) {
            $lineAddr .= '  ';
            $lineRule .= '  ';
        }
    }

    return [$lineAddr, $lineRule];
}

/**
 * Compare two arrays of hex-dump lines side by side, highlighting differences.
 *
 * Any line that differs will be suffixed with "  <-- DIFFERENT".
 * If one array is shorter, the missing lines are considered different.
 *
 * @param string[] $lines1 Hex-dump lines of file1.
 * @param string[] $lines2 Hex-dump lines of file2.
 * @return void
 */
function compareHexDumps(array $lines1, array $lines2): void
{
    $max = max(count($lines1), count($lines2));

    for ($i = 0; $i < $max; $i++) {
        $l1 = $lines1[$i] ?? '';
        $l2 = $lines2[$i] ?? '';

        $same = ($l1 === $l2);
        // We can visually separate them, or just keep them side by side
        // Example: left line  /  right line
        // Or highlight differences with an annotation

        if ($same) {
            // echo sprintf("%-5s | %-5s\n", rtrim($l1), rtrim($l2));
        } else {
            echo sprintf("%-5s | %-5s  <-- DIFFERENT\n", rtrim($l1), rtrim($l2));
        }
    }
}

/**
 * Computes an array of file offsets for reading samples.
 * The first 2 blocks and the last 2 blocks are always included.
 *
 * @param int $fileSize
 * @param int $sampleCount
 * @param int $sampleSize
 *
 * @return array<int>
 */
function computeSampleOffsets(int $fileSize, int $sampleCount = 10, int $sampleSize = 4096): array
{
    // initialize with the first 2 blocks and the last 2 blocks
    $offsets = [];
    $offsets[] = 0;
    $offsets[] = $sampleSize;

    // evenly distribute the rest of the samples among $sampleSize * 2 <-> $fileSize - $sampleSize * 2
    $step = ($fileSize - 4 * $sampleSize) / $sampleCount;
    for ($i = 1; $i <= $sampleCount; $i++) {
        $offsets[] = (int)round($i * $step) + $sampleSize;
    }

    // add the last 2 blocks
    $offsets[] = $fileSize - 2 * $sampleSize;
    $offsets[] = $fileSize - $sampleSize;

    return $offsets;
}
