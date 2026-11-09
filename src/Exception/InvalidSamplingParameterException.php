<?php

declare(strict_types=1);

namespace Mfonte\SparseDigest\Exception;

/**
 * Thrown when the sampling parameters (coverage or sample size) are out of range.
 */
class InvalidSamplingParameterException extends InvalidArgumentException
{
}
