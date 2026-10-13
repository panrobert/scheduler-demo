<?php

declare(strict_types=1);

namespace Emorfiq\Mutex\Store;

use Emorfiq\Common\Clock;
use Emorfiq\Common\Duration;
use Emorfiq\Mutex\LockKey;
use Emorfiq\Mutex\LockOwner;

/**
 * Array-backed lock store for tests and single-process demos.
 *
 * It is a faithful model of the contract — atomic by virtue of PHP being
 * single-threaded within a request, owner-checked, and TTL-aware — but it lives
 * in one process's memory, so it provides NO cross-process or cross-host
 * locking. Swap in PdoLockStore / RedisLockStore for that; nothing above this
 * interface changes.
 *
 * @internal
 */
final class InMemoryLockStore implements LockStore
{
    /** @var array<string, array{owner: string, expiresAt: int}> */
    private array $locks = [];

    public function __construct(private readonly Clock $clock)
    {
    }

    public function acquire(LockKey $key, LockOwner $owner, Duration $ttl): bool
    {
        $name = $key->toString();

        if ($this->isHeld($name)) {
            return false;
        }

        $this->locks[$name] = [
            'owner' => $owner->toString(),
            'expiresAt' => $this->now() + $ttl->toSeconds(),
        ];

        return true;
    }

    public function extend(LockKey $key, LockOwner $owner, Duration $ttl): bool
    {
        $name = $key->toString();

        if (!$this->isHeldBy($name, $owner)) {
            return false;
        }

        $this->locks[$name]['expiresAt'] = $this->now() + $ttl->toSeconds();

        return true;
    }

    public function release(LockKey $key, LockOwner $owner): bool
    {
        $name = $key->toString();

        if (!$this->isHeldBy($name, $owner)) {
            return false;
        }

        unset($this->locks[$name]);

        return true;
    }

    private function isHeld(string $name): bool
    {
        if (!isset($this->locks[$name])) {
            return false;
        }

        if ($this->locks[$name]['expiresAt'] <= $this->now()) {
            unset($this->locks[$name]); // lazy expiry: an expired lock is free.
            return false;
        }

        return true;
    }

    private function isHeldBy(string $name, LockOwner $owner): bool
    {
        return $this->isHeld($name)
            && hash_equals($this->locks[$name]['owner'], $owner->toString());
    }

    private function now(): int
    {
        return $this->clock->now()->getTimestamp();
    }
}
