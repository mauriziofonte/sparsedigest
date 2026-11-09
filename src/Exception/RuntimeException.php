<?php

declare(strict_types=1);

namespace Mfonte\SparseDigest\Exception;

/**
 * Thrown when an error occurs at runtime, typically while reading a file.
 *
 * Extends the SPL exception, so existing `catch (\RuntimeException $e)` blocks keep working.
 */
class RuntimeException extends \RuntimeException implements SparseDigestException
{
}
