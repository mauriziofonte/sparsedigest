<?php

declare(strict_types=1);

namespace Mfonte\SparseDigest\Exception;

/**
 * Thrown when an argument passed to the library is invalid.
 *
 * Extends the SPL exception, so existing `catch (\InvalidArgumentException $e)` blocks keep working.
 */
class InvalidArgumentException extends \InvalidArgumentException implements SparseDigestException
{
}
