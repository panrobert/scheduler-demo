<?php

declare(strict_types=1);

namespace Emorfiq\Mutex;

use Emorfiq\Mutex\Exception\LockNotAcquired;

/**
 * The application-facing port for distributed locking.
 *
 * Two entry points, one difference: how failure is reported.
 *  - tryAcquire() returns null when the key is held. This is the only method the
 *    scheduler uses — a held tick/task lock means "someone else is on it, skip",
 *    which is control flow, not an error.
 *  - acquire() throws LockNotAcquired instead, for callers that genuinely cannot
 *    proceed without the lock.
 *
 * The contract guarantees the four properties of a correct distributed lock:
 *   1. Owner token       — only the acquirer may release (enforced by LockHandle)
 *   2. Atomic acquire    — one indivisible compare-and-set (enforced by LockStore)
 *   3. TTL               — a crashed holder cannot block the key forever
 *   4. Extend            — a run longer than its TTL can keep its lock (refresh)
 */
interface Mutex
{
    /**
     * @throws LockNotAcquired
     */
    public function acquire(LockKey $key, LockOptions $options): LockHandle;

    public function tryAcquire(LockKey $key, LockOptions $options): ?LockHandle;
}
