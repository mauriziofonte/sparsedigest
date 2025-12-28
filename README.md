# SparseDigest

**SparseDigest** is a PHP library that provides **fast** file hashing by intelligently **sampling chunks of data** instead of hashing the entire content.

> [!TIP]
> In a hurry? Go to [Installation](#installation) and [Usage Examples](#usage-examples) to get started quickly!

SparseDigest is a *sort-of* [Locality-Sensitive Hashing](https://en.wikipedia.org/wiki/Locality-sensitive_hashing) algorithm for file hashing, a subset of [fuzzy hashing](https://en.wikipedia.org/wiki/Locality-sensitive_hashing#Fuzzy_hashing). Some concepts have also been taken from [Rolling Hashes](https://en.wikipedia.org/wiki/Rolling_hash).

It's designed to be **significantly faster** than traditional full-file hashing, especially for large files, by using a technique called *sampled hashing*. This involves hashing only specific portions (samples) of the file, rather than the entire content.

For large files (typically binary or compressed), the sampling approach **dramatically reduces I/O overhead**. For text-based files, where even a minor modification outside the sampled regions may be critical, SparseDigest automatically falls back to a full-file hash.

The implementation is **deterministic**. On the same input, with the same sampling parameters, it will always produce the same hash.

Computational complexity is **sub-linear** relative to file size. While larger files require slightly more time (more sample offsets to compute), the time increase is much smaller than proportional to file size. For example, a 100MB file takes only ~4-5× longer than a 1MB file to hash, rather than 100× longer with full-file hashing.

For a **detailed performance comparison** of *Sampled Hashing* vs *Native Hashing* (Full Hash), refer to [PERFORMANCE.md](PERFORMANCE.md).

---

## Performance Highlights

Based on real benchmarks (PHP 8.3, PHPBench 1.2.14):

| Scenario | SparseDigest | Native hash_file | Winner |
|----------|--------------|------------------|--------|
| 1MB binary file | ~37μs | ~111μs (xxh3) | ✅ SparseDigest 3× faster |
| 5MB binary file | ~174μs | ~806μs (xxh3) | ✅ SparseDigest 4.6× faster |
| 10MB binary file | ~399μs | ~1.8ms (xxh3) | ✅ SparseDigest 4.5× faster |
| 100MB binary file | ~4.8ms | ~18.2ms (xxh3) | ✅ SparseDigest 3.8× faster |
| Batch of large media (3×100MB) | ~12.4ms | ~47ms | ✅ SparseDigest 3.8× faster |
| Repeated hashing (5×) | ~28.3ms | ~110.7ms | ✅ SparseDigest 3.9× faster |
| Small files (<500KB) | ~16μs | ~13μs | ❌ Native 1.3× faster |

**Key insights:**

- **Break-even point: ~500KB-1MB** — SparseDigest becomes faster than native `hash_file()` for files larger than this threshold.
- **Constant memory usage: ~1.85MB** — Memory is now constant regardless of file size, thanks to incremental hashing.
- **Algorithm choice is irrelevant for sparse hashing** — With only 10% of data processed, all algorithms perform similarly.
- **Massive speedup on large files** — Up to 5× faster than native `hash_file(xxh3)` on typical file sizes.

> [!IMPORTANT]
> When to use native `hash_file()` instead:
>
> - Files smaller than 500KB
> - Text/document files (library falls back anyway)
> - Cryptographic integrity requirements

---

## TL;DR

```bash
composer require mfonte/sparsedigest
```

A minimum **PHP 7.2** environment is required, with **mbstring** support preferred. Anyway, `symfony/polyfill-mbstring` is used to provide fallbacks for `mbstring` functions.

Minimal usage example:

```php
use Mfonte\SparseDigest\SparseHasher;
use Mfonte\SparseDigest\FileHasher;

// Sparse hashing (fast, samples only portions of the file)
$sd = SparseHasher::new('/path/to/file');
$sparseHash = $sd->hash();
echo "Sparse Hash: " . $sparseHash->toHex() . "\n";

// Full-file hashing (reads entire file, cryptographically accurate)
$fd = FileHasher::new('/path/to/file');
$fullHash = $fd->hash();
echo "Full Hash: " . $fullHash->toHex() . "\n";
```

For a complete list of examples, refer to the [Usage Examples](#usage-examples) section.

### Key Features

- **Sparse Hashing** (`SparseHasher`):  
  - **hash()**: Computes a **sampled hash** using the fastest available algorithm. Accepts `coverage` (1-100%) and `sampleSize` parameters.
  - **hashAs($algo)**: Same as `hash()`, but uses the specified algorithm for the output representation.
  - For files detected as text, or too small to be sampled, automatically falls back to **full-file** hashing.

- **Full Hashing** (`FileHasher`):  
  - **hash()**: Computes a complete hash of the entire file using the fastest available algorithm.
  - **hashAs($algo)**: Same as `hash()`, but uses the specified algorithm (e.g., 'sha256', 'md5').
  - Use this when you need cryptographic accuracy or must detect any single-byte change.

- **File Abstraction** (`File`):  
  Low-level class for file operations: reading, hashing, MIME detection, and binary/text detection.

- **Performance**:  
  Dramatically reduced I/O overhead for large binary files through intelligent sampling.

---

## Visual Representation: Full-File Hash vs. Sampled Hash (240KB File)

**File Size:** 245760 bytes (can be divided into 60 blocks of 4096 bytes each)  
**Coverage:** 10% (default) — determines how many blocks are actually sampled  
**Sample Size:** 4096 bytes (default block size)

**Calculation:**

- Total sample bytes = 245760 × 10% = 24576 bytes
- Total blocks to sample = 24576 / 4096 = **6 blocks**
- Distribution: 2 first blocks + 2 last blocks + 2 intermediate blocks

**Legend:**

- `██` : Data block used in the hash calculation (4096 bytes each)
- `░░` : Data block not used in the hash calculation

### Full-File Hashing (60 blocks × 4096 bytes = 245760 bytes)

```txt
████████████████████████████████████████████████████████████████████████████████████████████████████████████████████████
Total data read: 245760 bytes (all 60 blocks)
```

### Sampled Hashing (6 blocks at 10% coverage)

```txt
████░░░░░░░░░░░░░░░░░░░░██░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░██░░░░░░░░░░████
|   |                   |                                                                             |         |   |
First 2 blocks          Intermediate 1                                                                Interm. 2 Last 2

Total data read: 24576 bytes (~10% of file, 6 blocks)
```

> [!WARNING]
> **Caveat about change detection**:
>
> Because sparse hashing only reads sampled regions, **modifications in non-sampled areas will NOT be detected**. This is by-design: the algorithm trades detection granularity for speed. If your use case requires detecting every single-byte change anywhere in the file, use full-file hashing instead (`FileHasher::new($file)->hash()`).

It is evident that the sampled hashing technique relies on *fractions* of a file, assuming that relevant changes are likely to be captured in the sampled regions. This approach is particularly effective for these scenarios:

- **Compressed Archives**: Where a minor change can result in a completely different file.
- **Compressed Images** (e.g., JPEG): Where a small change can affect the entire image (think about the metadata, or a single pixel change in a large image).
- **Compressed Videos**: Where a minor edit can alter the entire video.
- **Large Binary Files**: Where the cost of reading the entire file is prohibitive, and you can tolerate a small chance of false negatives.

---

## Underlying Logic of Sampled Hashing in SparseDigest

SparseDigest's hashing strategy is built around the concept of **sampled hashing**. Instead of processing every byte of a file, the algorithm reads only selected portions, significantly reducing I/O overhead for large files. The following outlines the precise, step‐by‐step logic of the implementation:

### 1. File Sampling Determination

- **Minimum File Size Check:**  
  The file is deemed too small to sample based on the `coverage` percentage and `sampleSize`. The algorithm calculates: `totalSampleBytes = floor(fileSize × coverage / 100)`, then `totalBlocks = floor(totalSampleBytes / sampleSize)`. If `totalBlocks < 4`, the file is too small for sparse hashing and falls back to full-file hashing.

- **Binary File Detection:**  
  Prior to sampling, the file is examined via the `isLikelyBinary()` method, which reads the first 512 bytes and applies a fast heuristic:
  - If any null byte (`\0`) is found, the file is immediately classified as binary (covers images, videos, archives, executables).
  - If control characters (ASCII < 9 or 14-31) are found, the file is classified as binary.
  - Otherwise, the file is considered text.
  
  If the file is detected as text, the library falls back to full-file hashing to ensure that even subtle modifications (which might occur outside the sampled regions) are caught.

### 2. Sample Selection and Offset Calculation

- **Fixed Edge Blocks:**  
  The algorithm always includes the first two blocks (starting at offset 0 and offset = sampleSize) and the last two blocks (offsets at `fileSize − 2 × sampleSize` and `fileSize − sampleSize`). These edge blocks typically contain file headers and footers, which are critical in many binary formats.

- **Evenly Distributed Intermediate Samples:**  
  The number of intermediate samples is calculated as: `intermediateCount = totalBlocks - 4` (subtracting the 4 fixed edge blocks). These intermediate blocks are evenly distributed between the fixed edge blocks. This ensures that the sampled regions provide a representative snapshot of the file's interior content.

### 3. Reading Data from Each Sample Block

- **Direct Block Reading:**  
  Each sample block is read directly via `readBytesAtOffset()`, which performs a simple `fseek()` + `fread()` operation. This approach is optimized for speed:
  - No preprocessing or transformation is applied to the raw bytes.
  - The data is read exactly as stored on disk.
  - This minimizes I/O overhead and CPU processing time.

### 4. Incremental Hashing

- **Single-Pass Incremental Hashing:**  
  Instead of computing intermediate hashes and concatenating them, SparseDigest uses PHP's incremental hashing API (`hash_init`, `hash_update`, `hash_final`):
  - A hash context is initialized with the selected algorithm.
  - Each sample block is fed directly to `hash_update()` as it's read.
  - The file size is appended as the final piece of data to enhance uniqueness.
  - `hash_final()` produces the final digest.
  
  This approach eliminates intermediate string allocations and reduces memory usage to a constant ~1.85MB regardless of file size.

### 5. Determinism and Performance

- **Determinism:**  
  Given the same file and the same sampling parameters (`coverage` and `sampleSize`), the algorithm always selects the same sample offsets and produces the same hash output.  
- **Performance:**  
  By only reading a fraction of the file (determined by `coverage`), the I/O overhead is drastically reduced. With the default 10% coverage, SparseDigest is 3-5× faster than native `hash_file()` for files larger than ~500KB. Memory usage is constant at ~1.85MB regardless of file size.

### 6. Non-Cryptographic Nature

It is critical to note that while SparseDigest is optimized for speed and efficiency in detecting file changes, **it is not designed for cryptographic security**. The sampled hash is a *heuristic representation* and may not be suitable for applications requiring cryptographic integrity.

---

## Summary

SparseDigest implements a **sampled hashing** strategy that:

- Selects a fixed set of edge samples (first 2 and last 2 blocks) and evenly distributes intermediate samples based on `coverage`.
- Reads each sample block directly without preprocessing, feeding data incrementally to the hash function.
- Uses incremental hashing (`hash_init`/`hash_update`/`hash_final`) for constant memory usage.
- Automatically falls back to full-file hashing for text-based files or files that are too small to sample.
- Guarantees determinism with minimal I/O overhead, making it ideal for large binary files where performance is a priority.
- **Does not detect changes in non-sampled regions** — this is an intentional trade-off for speed.

This highly opinionated approach prioritizes speed and practical consistency over cryptographic security, offering an efficient method to detect file changes while balancing I/O performance and data uniqueness.

## Installation

Install via Composer:

```bash
composer require mfonte/sparsedigest
```

Minimum requirements:

- **PHP 7.2** or higher
- **mbstring** support (preferred, but not mandatory)
- Nothing else!

## Usage Examples

### Sparse Hashing (Sampled Hash)

```php
use Mfonte\SparseDigest\SparseHasher;

$sd = SparseHasher::new('/path/to/file');

// Uses default sampling parameters (10% coverage, 4096 byte blocks)
// Returns a HashResult using the fastest available algorithm
$digest = $sd->hash();
echo "Sparse Hash: " . $digest->toHex() . "\n";

// With custom sampling parameters (20% coverage, 8192 byte blocks)
$digest = $sd->hash(20, 8192);
echo "Sparse Hash (20% coverage): " . $digest->toHex() . "\n";

// With a specific output algorithm (SHA256)
$digest = $sd->hashAs('sha256');
echo "Sparse Hash (SHA256): " . $digest->toHex() . "\n";

// Combining custom sampling and algorithm
$digest = $sd->hashAs('md5', 15, 2048);
echo "Sparse Hash (MD5, 15% coverage): " . $digest->toHex() . "\n";
```

**NOTE**: The hash output **will differ** when changing sampling parameters. This is expected behavior.

> **Heads up!**
>
> If you intend to persist sparse hashes (for example, in a database), remember to store the sampling parameters used.
> For re-hashing and comparison, you should use the same `coverage` and `sampleSize` values to obtain consistent results.

### Full File Hashing

For cryptographic accuracy or when you need to detect any single-byte change, use `FileHasher`:

```php
use Mfonte\SparseDigest\FileHasher;

$fd = FileHasher::new('/path/to/file');

// Full hash with the fastest available algorithm
$digest = $fd->hash();
echo "Full Hash: " . $digest->toHex() . "\n";

// Full hash with a specific algorithm
$digest = $fd->hashAs('sha256');
echo "Full Hash (SHA256): " . $digest->toHex() . "\n";

// Full hash with MD5
$digest = $fd->hashAs('md5');
echo "Full Hash (MD5): " . $digest->toHex() . "\n";
```

### Low-Level File Operations with File

The `File` class provides low-level access to file operations:

```php
use Mfonte\SparseDigest\File;

$file = File::new('/path/to/file');

// File information
echo "Size: " . $file->getSize() . " bytes\n";
echo "MIME: " . $file->getMimeType() . "\n";
echo "Binary: " . ($file->isLikelyBinary() ? 'yes' : 'no') . "\n";

// Full-file hash (alternative to FileHasher)
$digest = $file->hashWith('xxh3');
echo "Hash: " . $digest->toHex() . "\n";
```

## Order of preference for hashing algorithms

The library will use the fastest available algorithm from the following list, in ordered sequence (the first, the fastest):

```text
'xxh3', 'xxh128', 'crc32c', 'xxh64', 'crc32', 'crc32b', 'murmur3f', 'xxh32',
'murmur3c', 'murmur3a', 'adler32', 'md4', 'fnv1a64', 'fnv132', 'fnv164', 'fnv1a32',
'joaat', 'md5', 'tiger128,3', 'tiger160,3', 'tiger192,3', 'sha1', 'tiger160,4',
'tiger128,4', 'tiger192,4', 'ripemd128', 'ripemd256', 'sha3-224', 'sha3-256',
'sha384', 'sha512/224', 'sha512/256', 'sha512', 'ripemd320', 'ripemd160', 'sha3-384',
'haval128,3', 'haval160,3', 'haval192,3', 'haval224,3', 'haval256,3', 'sha224',
'sha256', 'sha3-512', 'haval128,4', 'haval160,4', 'haval192,4', 'haval224,4',
'haval256,4', 'haval128,5', 'haval160,5', 'haval192,5', 'haval224,5', 'haval256,5',
'whirlpool', 'gost', 'gost-crypto', 'snefru', 'snefru256', 'md2'
```

This pre-defined list has been derived from [https://php.watch/articles/php-hash-benchmark](https://php.watch/articles/php-hash-benchmark).

## Testing and Benchmarking

Run the test suite with:

```bash
composer test
```

Run the full benchmark suite with:

```bash
composer bench
```

Run specific benchmark groups with:

```bash
composer bench:core
composer bench:scaling
composer bench:algorithms
composer bench:realworld
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
