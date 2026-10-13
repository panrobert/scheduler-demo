<?php

declare(strict_types=1);

namespace Emorfiq\Mutex;

use Emorfiq\Common\Clock;
use Emorfiq\Common\Duration;
use Emorfiq\Mutex\Exception\LockNotAcquired;
use Emorfiq\Mutex\Store\LockStore;

/**
 * The one Mutex implementation. It owns the retry/wait policy and the minting of
 * owner tokens; all the atomic, backend-specific work is delegated to a
 * LockStore. Point it at InMemoryLockStore, PdoLockStore or RedisLockStore
 * without changing a line here.
 */
final class StoreMutex implements Mutex
{
    private const DEFAULT_RETRY = 100; // milliseconds between attempts while waiting.

    public function __construct(
        private readonly LockStore $store,
        private readonly Clock $clock,
    ) {
    }

    public function acquire(LockKey $key, LockOptions $options): LockHandle
    {
        return $this->tryAcquire($key, $options)
            ?? throw LockNotAcquired::forKey($key);
    }

    public function tryAcquire(LockKey $key, LockOptions $options): ?LockHandle
    {
        $owner = LockOwner::generate();
        $deadline = $this->deadline($options);

        do {
            if ($this->store->acquire($key, $owner, $options->ttl)) {
                return new StoreLockHandle(
                    $this->store,
                    $key,
                    $owner,
                    $this->clock->now()->add(
                        new \DateInterval('PT' . $options->ttl->toSeconds() . 'S')
                    ),
                    $this->clock,
                    $options->ttl,
                );
            }

            // No wait window => a single attempt. This is the scheduler's path:
            // a held lock means "skip", not "block".
            if ($deadline === null) {
                return null;
            }

            usleep($this->retryMicros($options));
        } while ($this->clock->now() < $deadline);

        return null;
    }

    private function deadline(LockOptions $options): ?\DateTimeImmutable
    {
        if ($options->waitTimeout === null) {
            return null;
        }

        return $this->clock->now()->add(
            new \DateInterval('PT' . $options->waitTimeout->toSeconds() . 'S')
        );
    }

    private function retryMicros(LockOptions $options): int
    {
        $ms = $options->retryInterval?->toSeconds() !== null
            ? $options->retryInterval->toSeconds() * 1000
            : self::DEFAULT_RETRY;

        return $ms * 1000;
    }
}
