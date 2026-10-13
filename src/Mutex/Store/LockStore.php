<?php

declare(strict_types=1);

namespace Emorfiq\Mutex\Store;

use Emorfiq\Common\Duration;
use Emorfiq\Mutex\LockKey;
use Emorfiq\Mutex\LockOwner;

/**
 * @internal The storage port behind the mutex. Application code never sees it —
 *           it holds a Mutex and a LockHandle, never a LockStore.
 *
 * Three rules make this contract safe to implement on top of any backend:
 *
 *  1. Every method is ATOMIC — it must map to a single storage operation
 *     (Redis SET NX / EVAL, a single conditional SQL UPDATE, memcached CAS).
 *     No read-then-write in PHP: that race is exactly what an unreliable mutex
 *     gets wrong.
 *
 *  2. There is deliberately NO get() that returns lock state. If a driver could
 *     read the owner and then decide, every driver would be tempted to implement
 *     check-then-set in PHP and reintroduce the race. Removing the method removes
 *     the temptation — acquire/extend/release each carry their own condition.
 *
 *  3. extend() and release() are conditional on owner match. They return false,
 *     changing nothing, when the caller is not the current holder. That single
 *     bool is how property #1 (owner token) is enforced at the storage layer.
 *
 * How each distributed-lock property is enforced here:
 *   Owner token  -> the LockOwner argument on every method
 *   Atomic CAS   -> the "single operation" rule above
 *   TTL          -> the Duration argument on acquire() / extend()
 *   Extend       -> extend()
 */
interface LockStore
{
    /**
     * Take the lock for $owner with lifetime $ttl.
     *
     * @return bool true only when the key was free or its previous TTL had
     *              already passed; false when a live lock is held by anyone
     *              (including the same owner — re-acquisition is not a no-op).
     */
    public function acquire(LockKey $key, LockOwner $owner, Duration $ttl): bool;

    /**
     * Extend the lock's TTL, but only if $owner still holds it.
     *
     * @return bool false (changing nothing) when $owner is not the current
     *              holder or the lock has expired.
     */
    public function extend(LockKey $key, LockOwner $owner, Duration $ttl): bool;

    /**
     * Release the lock, but only if $owner still holds it.
     *
     * @return bool false (changing nothing) when $owner is not the current
     *              holder or the lock is already gone.
     */
    public function release(LockKey $key, LockOwner $owner): bool;
}
