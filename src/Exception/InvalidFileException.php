<?php

declare(strict_types=1);

namespace Mfonte\SparseDigest\Exception;

/**
 * Thrown when the given file cannot be used: it does not exist, is not a regular file,
 * is not readable, or the input is neither a path nor an SplFileInfo.
 */
class InvalidFileException extends InvalidArgumentException
{
}
