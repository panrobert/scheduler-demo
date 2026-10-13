<?php

declare(strict_types=1);

namespace Emorfiq\Mutex;

use Emorfiq\Common\Clock;
use Emorfiq\Common\Duration;
use Emorfiq\Mutex\Exception\LockLost;
use Emorfiq\Mutex\Store\LockStore;

/**
 * The LockHandle returned by StoreMutex. It is the only object that knows the
 * owner token for this acquisition, which is what prevents one caller releasing
 * another's lock.
 *
 * Not readonly: refresh() advances $expiresAt, and release() flips $released.
 */
final class StoreLockHandle implements LockHandle
{
    private bool $released = false;

    public function __construct(
        private readonly LockStore $store,
        private readonly LockKey $key,
        private readonly LockOwner $owner,
        private \DateTimeImmutable $expiresAt,
        private readonly Clock $clock,
        private readonly Duration $ttl,
    ) {
    }

    public function getKey(): LockKey
    {
        return $this->key;
    }

    public function getExpiresAt(): \DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function refresh(?Duration $ttl = null): void
    {
        $ttl ??= $this->ttl;

        // extend() is conditional on owner match AND liveness. A false here means
        // the TTL passed before we got round to refreshing: we have lost the lock
        // and may now be racing another holder. Surface it.
        if (!$this->store->extend($this->key, $this->owner, $ttl)) {
            throw LockLost::forKey($this->key);
        }

        $this->expiresAt = $this->clock->now()->add(
            new \DateInterval('PT' . $ttl->toSeconds() . 'S')
        );
    }

    public function release(): void
    {
        if ($this->released) {
            return; // idempotent: releasing twice is a no-op.
        }

        $this->released = true;

        // Best-effort by design. The store returns false both when the lock has
        // simply expired (nobody holds it — releasing is pointless but harmless)
        // and when a different owner now holds it. With no get() on LockStore
        // those two cases are indistinguishable, so release() cannot honour the
        // PDF's "throw LockLost only on a different owner" clause without
        // reintroducing a read. We therefore keep release() silent and keep the
        // overrun incident signal in refresh(), where it is unambiguous.
        $this->store->release($this->key, $this->owner);
    }
}
