<?php

namespace Mfonte\SparseDigest\Strategy;

use Mfonte\SparseDigest\File;
use Mfonte\SparseDigest\HashingStrategy;
use Mfonte\SparseDigest\HashResult;
use RuntimeException;

/**
 * SparseStrategy - Sparse (sampled) file hashing strategy.
 *
 * Implements sparse (partial) file digesting using a sampling strategy.
 * The algorithm always samples the first two and last two blocks, with intermediate
 * blocks evenly distributed. The file size is appended to the intermediate results to enhance uniqueness.
 *
 * For files that are too small for the desired coverage, a full-file hash is computed.
 */
class SparseStrategy extends AbstractStrategy implements HashingStrategy
{
    /**
     * {@inheritdoc}
     */
    public function hash(File $file, int $coverage = 10, int $sampleSize = 4096): HashResult
    {
        $this->preventInvalidCoverage($coverage);
        $this->preventInvalidSampleSize($sampleSize);

        if ($file->cantBeSampled($coverage, $sampleSize) || !$file->isLikelyBinary()) {
            // Fallback to full hash.
            return $this->fullHash($file);
        }

        return $this->sparseHash($file, $coverage, $sampleSize);
    }

    /**
     * {@inheritdoc}
     */
    public function hashAs(File $file, string $algo, int $coverage = 10, int $sampleSize = 4096): HashResult
    {
        $this->preventInvalidCoverage($coverage);
        $this->preventInvalidSampleSize($sampleSize);

        if ($file->cantBeSampled($coverage, $sampleSize) || !$file->isLikelyBinary()) {
            // Fallback to full hash.
            return $this->fullHash($file, $algo);
        }

        return $this->sparseHash($file, $coverage, $sampleSize, $algo);
    }

    /**
     * Computes a sparse hash using incremental hashing.
     *
     * Uses hash_init/hash_update/hash_final for optimal memory efficiency
     * and reduced string allocations.
     *
     * @param File $file
     * @param int $coverage Desired coverage percentage.
     * @param int $sampleSize Block size in bytes.
     * @param string|null $algo Optional hashing algorithm for the HashResult.
     *
     * @return HashResult
     */
    protected function sparseHash(File $file, int $coverage, int $sampleSize, ?string $algo = null): HashResult
    {
        $algorithm = $algo ?? $this->defaultAlgo;
        $offsets = $this->computeSampleOffsets($file, $coverage, $sampleSize);

        // Use incremental hashing - no intermediate strings
        $ctx = hash_init($algorithm);

        foreach ($offsets as $offset) {
            $chunk = $file->readBytesAtOffset($offset, $sampleSize);
            hash_update($ctx, $chunk);
        }

        // Append file size for uniqueness
        hash_update($ctx, (string) $file->getSize());

        return HashResult::new(hash_final($ctx, true));
    }

    /**
     * Computes a full-file hash.
     *
     * @param File $file
     * @param string|null $algo Optional hashing algorithm for the HashResult.
     *
     * @return HashResult
     */
    protected function fullHash(File $file, ?string $algo = null): HashResult
    {
        $result = $this->stopwatch(function () use ($file, $algo) {
            return hash_file($algo ?? $this->defaultAlgo, $file->getPath(), true);
        });
        return HashResult::new($result);
    }

    /**
     * Computes an array of offsets for sampling.
     *
     * This method calculates the total number of blocks based on the file size, desired coverage,
     * and sample size. It then guarantees the inclusion of the first two and last two blocks,
     * with intermediate blocks evenly distributed.
     *
     * @param File $file
     * @param int $coverage Desired coverage percentage.
     * @param int $sampleSize Block size in bytes.
     * @return array<int>
     * @throws RuntimeException if the file is too small.
     */
    protected function computeSampleOffsets(File $file, int $coverage, int $sampleSize): array
    {
        $fileSize = $file->getSize();
        // Calculate the total number of bytes to sample based on coverage.
        $totalSampleBytes = (int) floor($fileSize * $coverage / 100);
        // Determine how many full blocks we can sample.
        $totalBlocks = (int) floor($totalSampleBytes / $sampleSize);

        if ($totalBlocks < 4) {
            throw new RuntimeException('File size is too small for the desired sparse sampling based on the specified coverage and sample size.');
        }

        $intermediateCount = $totalBlocks - 4;

        // Ensure the file is large enough.
        if ($fileSize < $totalBlocks * $sampleSize) {
            throw new RuntimeException('File size is too small relative to the computed total number of blocks.');
        }

        $offsets = [];
        // First two blocks.
        $offsets[] = 0;
        $offsets[] = $sampleSize;

        // Compute intermediate offsets: evenly distributed between the first two and last two blocks.
        $start = 2 * $sampleSize;
        $end = $fileSize - 2 * $sampleSize;
        $step = ($end - $start) / ($intermediateCount + 1);
        for ($i = 1; $i <= $intermediateCount; $i++) {
            $offsets[] = (int) round($start + $i * $step);
        }

        // Last two blocks.
        $offsets[] = $fileSize - 2 * $sampleSize;
        $offsets[] = $fileSize - $sampleSize;

        return $offsets;
    }
}
