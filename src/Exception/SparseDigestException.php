<?php

declare(strict_types=1);

namespace Mfonte\SparseDigest\Exception;

use Throwable;

/**
 * Marker interface implemented by every exception thrown by this library.
 *
 * Catch it to handle any SparseDigest error in one place:
 *
 *     try {
 *         $hash = SparseHasher::new($path)->hash();
 *     } catch (SparseDigestException $e) {
 *         // ...
 *     }
 */
interface SparseDigestException extends Throwable
{
}
