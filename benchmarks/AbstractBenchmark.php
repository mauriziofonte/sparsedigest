<?php

declare(strict_types=1);

namespace Mfonte\SparseDigest\Benchmarks;

use RuntimeException;

/**
 * Base class for benchmark fixtures.
 * Provides utility methods for creating temporary files of various sizes.
 */
abstract class AbstractBenchmark
{
    /** @var array<string, string> Cache of temporary files by key */
    protected static $tempFiles = [];

    /** @var bool Whether cleanup has been registered */
    protected static $cleanupRegistered = false;

    /**
     * Creates a temporary file of the specified size with random binary content.
     *
     * @param int $size Size in bytes
     * @param string $key Cache key for reuse
     *
     * @return string Path to the temporary file
     */
    protected function createTempFile(int $size, string $key = ''): string
    {
        if ($key !== '' && isset(self::$tempFiles[$key])) {
            if (file_exists(self::$tempFiles[$key])) {
                return self::$tempFiles[$key];
            }
        }

        $tempFile = tempnam(sys_get_temp_dir(), 'sparsedigest_bench_');
        if ($tempFile === false) {
            throw new RuntimeException("Failed to create temporary file");
        }

        $handle = fopen($tempFile, 'wb');
        if ($handle === false) {
            throw new RuntimeException("Failed to open temporary file for writing");
        }

        // Write in chunks to avoid memory issues with large files
        $chunkSize = 1024 * 1024; // 1MB chunks
        $remaining = $size;

        while ($remaining > 0) {
            $writeSize = min($chunkSize, $remaining);
            fwrite($handle, random_bytes($writeSize));
            $remaining -= $writeSize;
        }

        fclose($handle);

        if ($key !== '') {
            self::$tempFiles[$key] = $tempFile;
        }

        $this->registerCleanup();

        return $tempFile;
    }

    /**
     * Creates a temporary file with text content.
     *
     * @param int $size Approximate size in bytes
     * @param string $key Cache key for reuse
     *
     * @return string Path to the temporary file
     */
    protected function createTempTextFile(int $size, string $key = ''): string
    {
        if ($key !== '' && isset(self::$tempFiles[$key])) {
            if (file_exists(self::$tempFiles[$key])) {
                return self::$tempFiles[$key];
            }
        }

        $tempFile = tempnam(sys_get_temp_dir(), 'sparsedigest_bench_');
        if ($tempFile === false) {
            throw new RuntimeException("Failed to create temporary file");
        }

        $handle = fopen($tempFile, 'wb');
        if ($handle === false) {
            throw new RuntimeException("Failed to open temporary file for writing");
        }

        // Generate pseudo-random text
        $words = ['Lorem', 'ipsum', 'dolor', 'sit', 'amet', 'consectetur', 'adipiscing', 'elit'];
        $remaining = $size;

        while ($remaining > 0) {
            $line = '';
            $wordsPerLine = mt_rand(5, 15);
            for ($i = 0; $i < $wordsPerLine; $i++) {
                $line .= $words[array_rand($words)] . ' ';
            }
            $line = trim($line) . "\n";
            fwrite($handle, $line);
            $remaining -= strlen($line);
        }

        fclose($handle);

        if ($key !== '') {
            self::$tempFiles[$key] = $tempFile;
        }

        $this->registerCleanup();

        return $tempFile;
    }

    /**
     * Registers cleanup function to remove temporary files on shutdown.
     *
     * @return void
     */
    private function registerCleanup(): void
    {
        if (! self::$cleanupRegistered) {
            register_shutdown_function(function (): void {
                foreach (self::$tempFiles as $file) {
                    if (file_exists($file)) {
                        @unlink($file);
                    }
                }
            });
            self::$cleanupRegistered = true;
        }
    }

    /**
     * Formats byte size for display.
     *
     * @param int $bytes
     *
     * @return string
     */
    protected function formatSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        $size = (float)$bytes;
        while ($size >= 1024 && $i < count($units) - 1) {
            $size /= 1024;
            $i++;
        }

        return round($size, 2) . ' ' . $units[$i];
    }
}
