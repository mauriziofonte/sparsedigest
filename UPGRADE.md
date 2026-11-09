# Upgrade guide

## From 1.x to 2.0

**Hashes do not change.** Every non-empty file produces exactly the same digest as in 1.x,
with the same algorithm and sampling parameters, so stored hashes remain valid.

### Classes are now `final`

`File`, `HashResult`, `SparseHasher`, `FileHasher`, `SparseStrategy` and `FullStrategy` are `final`,
and their former `protected` members are now `private`.

If you extended one of them, switch to composition, or implement
`Mfonte\SparseDigest\HashingStrategy` and pass your strategy to `SparseHasher::new($file, $strategy)`
or `FileHasher::new($file, $strategy)`. `AbstractStrategy` is still abstract and can be extended.

### Empty files no longer throw

In 1.x, `File::new()`, `SparseHasher::new()` and `FileHasher::new()` threw an
`InvalidArgumentException` for empty files. They are now accepted and get the digest of the
empty string. If you relied on the exception, check `File::new($path)->getSize() === 0` yourself.

### Exceptions

All exceptions now live in `Mfonte\SparseDigest\Exception` and implement `SparseDigestException`.
They extend the SPL exceptions thrown by 1.x, so existing `catch (\InvalidArgumentException $e)` and
`catch (\RuntimeException $e)` blocks keep working. Two cases change type:

| Situation                              | 1.x                                         | 2.0                                 |
|----------------------------------------|---------------------------------------------|-------------------------------------|
| `SplFileInfo` pointing to a missing file | `RuntimeException`                        | `InvalidFileException` (an `InvalidArgumentException`) |
| Invalid algorithm in `SparseHasher::hashAs()` | `ValueError` or a warning, depending on PHP | `UnsupportedAlgorithmException` (an `InvalidArgumentException`) |

Exception messages were also reworded, so do not match on them.

### Removed scripts

`scripts/hash.php` is replaced by the `vendor/bin/sparsedigest` command. `scripts/bench.php` and
`scripts/hexdump.php` were removed: they referenced classes that no longer existed and could not run.
Use `composer bench` for benchmarks.
