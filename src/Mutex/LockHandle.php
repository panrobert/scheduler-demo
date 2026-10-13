<?php

declare(strict_types=1);

namespace Emorfiq\Mutex;

use Emorfiq\Common\Duration;
use Emorfiq\Mutex\Exception\LockLost;

/**
 * Returned by acquire() / tryAcquire(). Carries the owner token internally, so
 * callers can neither forge it nor release someone else's lock — they can only
 * act on the lock they actually hold.
 */
interface LockHandle
{
    public function getKey(): LockKey;

    public function getExpiresAt(): \DateTimeImmutable;

    /**
     * Heartbeat for long runs: extends the TTL. null reuses the TTL from the
     * original acquisition.
     *
     * @throws LockLost if the TTL has already passed — you overran it and no
     *                  longer hold the lock. This is a signal to abort, not to
     *                  retry.
     */
    public function refresh(?Duration $ttl = null): void;

    /**
     * Releases the lock. Idempotent: calling it again, or after the lock has
     * already expired, does nothing.
     */
    public function release(): void;
}
