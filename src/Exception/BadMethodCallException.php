<?php

declare(strict_types=1);

namespace Mfonte\SparseDigest\Exception;

/**
 * Thrown when an unsupported operation is attempted (for example cloning or serializing a File).
 */
class BadMethodCallException extends \BadMethodCallException implements SparseDigestException
{
}
