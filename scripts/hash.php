#!/usr/bin/env php
<?php

/**
 * This file is part of the SparseDigest package.
 * Runs a SparseHasher instance on a single file and prints the hash.
*/

define('SCRIPT', realpath($argv[0]));
define('DS', DIRECTORY_SEPARATOR);
define('TD', rtrim(__DIR__, DS) . DS);
define('SRC', realpath(TD . '..' . DS) . DS . 'src' . DS);

// Manually load the SparseDigest classes
require_once SRC . 'HashResult.php';
require_once SRC . 'File.php';
require_once SRC . 'HashingStrategy.php';
require_once SRC . 'Strategy/AbstractStrategy.php';
require_once SRC . 'Strategy/SparseStrategy.php';
require_once SRC . 'Strategy/FullStrategy.php';
require_once SRC . 'SparseHasher.php';
require_once SRC . 'FileHasher.php';

$file = $argv[1] ?? null;
if (!$file) {
    echo "Usage: php hash.php <file>\n";
    exit(1);
}

if (!file_exists($file) || !is_readable($file)) {
    echo "File not found or not readable: {$file}\n";
    exit(1);
}

$fileObj = \Mfonte\SparseDigest\File::new($file);
$sparseHasher = \Mfonte\SparseDigest\SparseHasher::new($file);
$fileHasher = \Mfonte\SparseDigest\FileHasher::new($file);

$mime = $fileObj->getMimeType();
$size = $fileObj->getSize();
$isLikelyBinary = $fileObj->isLikelyBinary();
$cantBeSampled = $fileObj->cantBeSampled();

$startSparse = microtime(true);
$sparseHash = $sparseHasher->hashAs('xxh128');
$sparseHashTime = (microtime(true) - $startSparse) * 1000;

$startFull = microtime(true);
$fullHash = $fileHasher->hashAs('xxh128');
$fullHashTime = (microtime(true) - $startFull) * 1000;

echo "File: {$file}\n";
echo "Mime: {$mime}\n";
echo "Size: {$size} bytes\n";
echo "Sparse Hash: {$sparseHash}\n";
echo "Sparse Hash Time: " . round($sparseHashTime, 4) . " ms\n";
echo "Full Hash: {$fullHash}\n";
echo "Full Hash Time: " . round($fullHashTime, 4) . " ms\n";
echo "Is Likely Binary: " . ($isLikelyBinary ? 'Yes' : 'No') . "\n";
echo "Can be Sampled: " . ($cantBeSampled ? 'No (too small)' : 'Yes') . "\n";
exit(0);
