# Security Policy

## Supported versions

Only the latest minor release of the current major version receives security fixes.

| Version | Supported |
|---------|-----------|
| 2.x     | ✅        |
| 1.x     | ❌        |

## Scope

SparseDigest is **not** a cryptographic library. By design, a sparse hash ignores
the bytes that fall outside the sampled blocks, so two different files can
produce the same sparse hash. This is documented behaviour, not a vulnerability.
Do not use sparse hashes for integrity verification against a malicious party;
use `FileHasher` with a cryptographic algorithm such as `sha256` instead.

Reports that are in scope include, for example: reading outside the requested file,
path handling issues, or denial of service through crafted input.

## Reporting a vulnerability

Please **do not open a public issue**. Report it privately through
[GitHub Security Advisories](https://github.com/mauriziofonte/sparsedigest/security/advisories/new),
or by e-mail to fonte.maurizio@gmail.com.

You should receive an answer within 7 days. Once the issue is confirmed, a fix is
released as soon as possible and you will be credited in the advisory, unless you
prefer to remain anonymous.
