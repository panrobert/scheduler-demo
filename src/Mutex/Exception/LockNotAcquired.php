<?php

declare(strict_types=1);

namespace Emorfiq\Mutex\Exception;

use Emorfiq\Mutex\LockKey;

/**
 * Thrown by Mutex::acquire() when the lock could not be taken within the wait
 * window. tryAcquire() never throws this — it returns null instead.
 */
final class LockNotAcquired extends \RuntimeException
{
    public static function forKey(LockKey $key): self
    {
        return new self(sprintf('Could not acquire lock "%s".', $key->toString()));
    }
}
