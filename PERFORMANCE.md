# Performance Analysis - SparseDigest

This document presents **real benchmark results** from PHPBench 1.2.14 on PHP 8.4.13.

> **Important:**  
> SparseDigest is designed for fast change detection on **large binary files**. For small files (<1MB) or text files, native `hash_file()` is often faster. The library automatically falls back to full-file hashing for text files.

---

## Quick Summary

| Scenario | SparseDigest | Native hash_file | Winner |
|----------|--------------|------------------|--------|
| **1MB binary file** | ~37μs | ~111μs (xxh3) | ✅ SparseDigest 3× faster |
| **5MB binary file** | ~174μs | ~806μs (xxh3) | ✅ SparseDigest 4.6× faster |
| **10MB binary file** | ~399μs | ~1.8ms (xxh3) | ✅ SparseDigest 4.5× faster |
| **100MB binary file** | ~4.8ms | ~18.2ms (xxh3) | ✅ SparseDigest 3.8× faster |
| **Batch of large media (100MB×3)** | ~12.4ms | ~47ms | ✅ SparseDigest 3.8× faster |
| **Repeated hashing (5×)** | ~28.3ms | ~110.7ms | ✅ SparseDigest 3.9× faster |
| **Small files (<500KB)** | ~16μs | ~13μs | ❌ Native 1.3× faster |

---

## System Resource Efficiency

### Memory Usage (Constant ~1.85MB)

| File Size | SparseDigest Memory | Native hash_file Memory |
|-----------|---------------------|-------------------------|
| 1 MB | **~1.85 MB** | ~1.9 MB |
| 10 MB | **~1.85 MB** | ~1.9 MB |
| 50 MB | **~1.85 MB** | ~1.9 MB |
| 100 MB | **~1.85 MB** | ~1.9 MB |
| 250 MB | **~1.85 MB** | ~1.9 MB |

**Key insight:** SparseDigest now uses **constant memory** (~1.85MB) regardless of file size, thanks to incremental hashing with `hash_init/hash_update/hash_final`. This is on par with native `hash_file()` which streams the file.

### CPU Efficiency

SparseDigest performs:

- Fewer I/O operations (reads only sampled portions)
- Less hashing computation (processes ~10% of data by default)
- Incremental hashing (no intermediate string concatenation)
- More seek operations (jumps between sample positions)

---

## Detailed Benchmark Results

### File Size Scaling (10% Coverage, xxh3 Algorithm)

| File Size | SparseDigest | Native hash_file | Speedup |
|-----------|--------------|------------------|---------|
| 1 KB | 5.8 μs | **2.2 μs** | Native 2.6× faster |
| 10 KB | 7.2 μs | **3.0 μs** | Native 2.4× faster |
| 100 KB | 16.4 μs | **12.8 μs** | Native 1.3× faster |
| 1 MB | **37 μs** | 111 μs | SparseDigest 3× faster |
| 5 MB | **174 μs** | 806 μs | SparseDigest 4.6× faster |
| 10 MB | **399 μs** | 1.8 ms | SparseDigest 4.5× faster |
| 25 MB | **1.04 ms** | 5.3 ms | SparseDigest 5× faster |
| 50 MB | **2.1 ms** | 9.7 ms | SparseDigest 4.6× faster |
| 100 MB | **4.8 ms** | 18.2 ms | SparseDigest 3.8× faster |
| 200 MB | **9.0 ms** | 35.3 ms | SparseDigest 4× faster |

**Break-even point: ~500KB-1MB** - SparseDigest becomes faster than native hashing.

---

### Coverage Level Impact (100MB File)

| Coverage | Time | Memory Used |
|----------|------|-------------|
| 1% | ~1.8 ms | ~2.4 MB |
| 2% | ~2.5 ms | ~3.1 MB |
| 5% | ~4.6 ms | ~6.4 MB |
| **10% (default)** | ~7.9 ms | ~11.7 MB |
| 15% | ~11.2 ms | ~17.1 MB |
| 20% | ~14.7 ms | ~22.5 MB |
| 25% | ~17.8 ms | ~27.8 MB |
| 30% | ~21.3 ms | ~33.1 MB |
| 50% | ~33.2 ms | ~54.6 MB |

Lower coverage = faster execution but less collision resistance.

---

### Sample Size Impact (10% Coverage, 100MB File)

| Sample Size | Time | Memory |
|-------------|------|--------|
| 512 B | 22.5 ms | ~9.7 MB |
| 1 KB | 14.8 ms | ~8.6 MB |
| 2 KB | 10.5 ms | ~8.1 MB |
| **4 KB (default)** | 7.6 ms | ~11.7 MB |
| 8 KB | 7.3 ms | ~9.0 MB |
| 16 KB | 8.9 ms | ~7.6 MB |
| 32 KB | 13.8 ms | ~11.9 MB |
| 64 KB | 24.9 ms | ~22.8 MB |

**Optimal sample size: 4-8 KB** - balances I/O efficiency and granularity.

---

### Algorithm Performance Comparison (50MB File, 10% Coverage)

#### Sparse Hashing (SparseDigest)

| Algorithm | Time | Notes |
|-----------|------|-------|
| **xxh64** | ~3.5 ms | Fastest |
| xxh3 | ~4.0 ms | Default choice |
| xxh128 | ~4.4 ms | 128-bit variant |
| crc32b | ~3.8 ms | Hardware accelerated |
| crc32c | ~3.9 ms | Hardware accelerated |
| adler32 | ~3.8 ms | Very fast checksum |
| murmur3f | ~3.9 ms | Fast non-crypto |
| md5 | ~3.9 ms | Legacy |
| sha256 | ~3.6 ms | Crypto |
| sha512 | ~3.9 ms | Crypto |
| sha3-256 | ~4.3 ms | Modern crypto |
| sha3-512 | ~3.9 ms | Modern crypto |

**Key insight:** With sparse hashing, algorithm choice has minimal impact because only 10% of data is processed.

#### Native hash_file (Full File)

| Algorithm | Time | vs xxh3 |
|-----------|------|---------|
| crc32c | ~2.9 ms | **Fastest** |
| crc32b | ~2.9 ms | Equal |
| crc32 | ~3.2 ms | +9% |
| **xxh3** | ~3.9 ms | baseline |
| xxh64 | ~4.1 ms | +3% |
| xxh128 | ~4.2 ms | +8% |
| xxh32 | ~5.2 ms | +33% |
| murmur3f | ~5.3 ms | +35% |
| murmur3a | ~8.0 ms | +103% |
| adler32 | ~10.9 ms | +178% |
| sha256 | ~14.7 ms | +277% |
| md4 | ~18.6 ms | +377% |
| md5 | ~28.5 ms | +628% |
| sha1 | ~30.2 ms | +672% |
| sha384 | ~48.6 ms | +1146% |
| sha512 | ~48.5 ms | +1141% |
| sha3-256 | ~52.8 ms | +1254% |
| sha3-512 | ~95.1 ms | +2336% |

**Key insight:** For native hashing, algorithm choice matters significantly. CRC32 variants and xxh family are the fastest.

---

## Real-World Scenarios

### Media File Batch (3× ~100MB Files)

| Method | Total Time | Memory Peak |
|--------|------------|-------------|
| **SparseDigest** | **12.4 ms** | ~1.85 MB |
| Native xxh3 | 47 ms | ~1.85 MB |

SparseDigest is **3.8× faster** with the same memory footprint.

### Document Batch (10× Small Files ~100KB Each)

| Method | Total Time | Memory Peak |
|--------|------------|-------------|
| SparseDigest | 938 μs | ~1.85 MB |
| **Native xxh3** | **792 μs** | ~1.85 MB |

Native is **1.2× faster** for small files - use `hash_file()` directly.

### Large Log File (50MB Single File)

| Method | Time | Notes |
|--------|------|-------|
| SparseDigest | 19.5 ms | Samples 10% |
| **Native xxh3** | **17.8 ms** | Reads sequentially |

Native is slightly faster for sequential log files due to I/O patterns.

### Repeated Hashing (Same Large File, 5 Times)

| Method | Total Time | Avg per Hash |
|--------|------------|--------------|
| **SparseDigest** | **28.3 ms** | ~5.7 ms |
| Native xxh3 | 110.7 ms | ~22 ms |

SparseDigest is **3.9× faster** when repeatedly hashing the same file.

---

## When to Use SparseDigest

### ✅ Best Use Cases

- **Binary files >500KB** - 3-5× faster than native `hash_file()`
- **Large media files (>5MB)** - Consistent 4-5× speedup
- **Batch processing large files** - Reduces total I/O time significantly
- **Repeated hashing** - ~4× faster than native
- **Change detection systems** - Fast fingerprinting

### ❌ When Native is Better

- **Very small files (<500KB)** - Native `hash_file()` is faster
- **Text/document files** - Library falls back to full hash anyway
- **Cryptographic requirements** - Always use full hash

---

## Running Benchmarks

```bash
# Run full benchmark suite
composer bench

# Specific benchmark groups
composer bench:core        # SparseDigest vs hash_file
composer bench:scaling     # File size scaling
composer bench:algorithms  # Algorithm comparison
composer bench:realworld   # Real-world scenarios

# Custom run with more iterations
./vendor/bin/phpbench run --iterations=10 --revs=20
```

---

## Technical Notes

### Benchmark Environment

- **PHP**: 8.4.13
- **PHPBench**: 1.2.14
- **xdebug**: disabled
- **opcache**: disabled
- **Iterations**: 5 per benchmark
- **Revolutions**: 10 per iteration

### Time Complexity

| Operation | Complexity |
|-----------|------------|
| SparseHasher::hash() | O(coverage × n) |
| hash_file() | O(n) |

Where `n` is file size. With fixed coverage (e.g., 10%), SparseDigest achieves **effective O(1)** relative to file size growth.
