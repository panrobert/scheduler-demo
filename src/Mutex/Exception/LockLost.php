<?php

declare(strict_types=1);

namespace Emorfiq\Mutex\Exception;

use Emorfiq\Mutex\LockKey;

/**
 * Thrown by LockHandle::refresh() when the TTL has already passed and the lock
 * can no longer be extended. This is the incident signal described in the PDF:
 * a run that overran its TTL may now be executing concurrently with another
 * holder, and the caller must find out.
 */
final class LockLost extends \RuntimeException
{
    public static function forKey(LockKey $key): self
    {
        return new self(sprintf(
            'Lock "%s" was lost: the TTL passed before it could be refreshed.',
            $key->toString()
        ));
    }
}
