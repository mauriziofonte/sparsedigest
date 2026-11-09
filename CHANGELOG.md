# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [2.0.0] - 2026-11-09

Hash values are unchanged: every non-empty file hashes to exactly the same digest as in 1.0.0,
so previously stored hashes remain valid. See [UPGRADE.md](UPGRADE.md) for the breaking changes.

### Breaking changes

- `File`, `HashResult`, `SparseHasher`, `FileHasher`, `SparseStrategy` and `FullStrategy` are now `final`,
  and their `protected` members are now `private`. Implement `HashingStrategy` to customize hashing.
- Empty files are hashed instead of throwing `InvalidArgumentException`.
- `scripts/hash.php`, `scripts/bench.php` and `scripts/hexdump.php` were removed
  (the last two were already broken: they referenced classes that no longer existed).

### Added

- Streams: `File::new()`, `SparseHasher::new()` and `FileHasher::new()` accept an open, readable and seekable
  stream. The stream is never closed by the library. `File::isStream()` tells whether a `File` wraps one.
- `SparseHasher::matches()` and `FileHasher::matches()` compare two files, checking sizes first.
- `vendor/bin/sparsedigest` command line tool (`sha256sum`-style output, `--json`, `--full`, `--algo`,
  `--coverage`, `--sample-size`), backed by `Mfonte\SparseDigest\Console\Application`.
- `UPGRADE.md`.
- Empty files are now supported: they get a full hash equal to the digest of the empty string.
- Library exception hierarchy under `Mfonte\SparseDigest\Exception`: every exception implements
  `SparseDigestException` and extends the matching SPL exception (backwards compatible with existing `catch` blocks).
  Specific types: `InvalidFileException`, `UnsupportedAlgorithmException`, `InvalidSamplingParameterException`,
  `InvalidRangeException`, `IOException`, `BadMethodCallException`.
- `HashResult::getAlgorithm()` and `HashResult::isSparse()`: which algorithm produced the digest, and whether
  it was sampled or fell back to a full-file hash.
- `HashResult::fromHex()`, `HashResult::fromBinary()` to rebuild stored hashes, and `HashResult::toBase64()`.
- `HashResult::equals()` for constant-time comparison against another `HashResult` or a hex string.
- `AbstractStrategy::PREFERRED_ALGORITHMS` constant exposing the algorithm preference list.
- GitHub Actions CI: tests on PHP 7.2 to 8.4 (Linux, plus Windows and macOS), lowest dependencies,
  PHPStan, PHP CS Fixer, `composer validate` and code coverage.
- `CHANGELOG.md`, `SECURITY.md`, `CODE_OF_CONDUCT.md`, issue and pull request templates,
  `.editorconfig`, `.gitattributes` (tests, benchmarks and tooling are no longer shipped in the Composer package).
- Composer scripts `check`, `cs:check`, `cs:fix`, `test:coverage`.

### Changed

- All source files declare `strict_types=1`.
- An invalid algorithm passed to `SparseHasher::hashAs()` now always throws `InvalidArgumentException`,
  whichever code path (sparse or full fallback) is taken. Previously it could surface as a PHP `ValueError`
  or warning depending on the PHP version.
- A non-existent `SplFileInfo` now throws `InvalidArgumentException` (like a string path) instead of a `RuntimeException`.
- Exception messages for missing and invalid inputs are more precise.
- `ext-mbstring` is no longer a runtime requirement (it was only used by the test suite); `ext-hash` is now declared.
- `ext-fileinfo` is suggested; `File::getMimeType()` no longer fatals when it is missing and
  returns `application/octet-stream` instead.
- PHPStan now runs at the maximum level on `src/` and `tests/`; coding style is PSR-12 based.

### Fixed

- `SparseHasher::getComputeTime()` always returned `0.0` (or a stale value) after a sparse hash: the sparse path was not timed.
- A sample size of `0` or a negative multiple of 64 passed validation and caused a `DivisionByZeroError`;
  it now throws `InvalidArgumentException`.
- `FullStrategy` did not check the return value of `hash_file()`; a read failure now throws `RuntimeException`.
- Tests no longer write to STDOUT and no longer require `ext-posix`.
- `scripts/analyzer.php` loads classes through the Composer autoloader (it missed the new classes) and
  writes its file list next to the script instead of the current directory.

### Removed

- The `version` field from `composer.json` (versions come from Git tags).
- The unused `php-coveralls/php-coveralls` dev dependency.

## [1.0.0] - 2025-12-28

- Initial release.

[2.0.0]: https://github.com/mauriziofonte/sparsedigest/compare/v1.0.0...v2.0.0
[1.0.0]: https://github.com/mauriziofonte/sparsedigest/releases/tag/v1.0.0
