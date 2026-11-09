<?php

declare(strict_types=1);

namespace Mfonte\SparseDigest\Exception;

/**
 * Thrown when a requested byte range (offset and length) is invalid or exceeds the file size.
 */
class InvalidRangeException extends InvalidArgumentException
{
}
