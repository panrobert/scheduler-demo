<?php

declare(strict_types=1);

namespace Emorfiq\Tests\Mutex;

use Emorfiq\Common\Duration;
use Emorfiq\Common\FrozenClock;
use Emorfiq\Mutex\Exception\LockLost;
use Emorfiq\Mutex\Exception\LockNotAcquired;
use Emorfiq\Mutex\LockKey;
use Emorfiq\Mutex\LockOptions;
use Emorfiq\Mutex\Store\InMemoryLockStore;
use Emorfiq\Mutex\StoreMutex;
use PHPUnit\Framework\TestCase;

final class StoreMutexTest extends TestCase
{
    private FrozenClock $clock;
    private StoreMutex $mutex;
    private LockKey $key;

    protected function setUp(): void
    {
        $this->clock = FrozenClock::at('2026-10-13 00:00:00');
        $this->mutex = new StoreMutex(new InMemoryLockStore($this->clock), $this->clock);
        $this->key = LockKey::fromString('scheduler:tick:2026-10-13T00:00');
    }

    public function testTryAcquireReturnsHandleThenNullWhileHeld(): void
    {
        $handle = $this->mutex->tryAcquire($this->key, new LockOptions(Duration::fromSeconds(60)));

        self::assertNotNull($handle);
        self::assertTrue($handle->getKey()->equals($this->key));
        self::assertNull($this->mutex->tryAcquire($this->key, new LockOptions(Duration::fromSeconds(60))));
    }

    public function testReleaseFreesTheKey(): void
    {
        $handle = $this->mutex->tryAcquire($this->key, new LockOptions(Duration::fromSeconds(60)));
        self::assertNotNull($handle);
        $handle->release();

        self::assertNotNull($this->mutex->tryAcquire($this->key, new LockOptions(Duration::fromSeconds(60))));
    }

    public function testReleaseIsIdempotent(): void
    {
        $handle = $this->mutex->tryAcquire($this->key, new LockOptions(Duration::fromSeconds(60)));
        self::assertNotNull($handle);

        $handle->release();
        $handle->release(); // second release must be a silent no-op, not an error.

        // The key is free after the (single) effective release.
        self::assertNotNull($this->mutex->tryAcquire($this->key, new LockOptions(Duration::fromSeconds(60))));
    }

    public function testRefreshExtendsExpiry(): void
    {
        $handle = $this->mutex->tryAcquire($this->key, new LockOptions(Duration::fromSeconds(60)));
        self::assertNotNull($handle);

        $this->clock->advance(Duration::fromSeconds(30));
        $handle->refresh(Duration::fromSeconds(60));

        self::assertSame(
            $this->clock->now()->add(new \DateInterval('PT60S'))->getTimestamp(),
            $handle->getExpiresAt()->getTimestamp()
        );
    }

    public function testRefreshAfterExpiryThrowsLockLost(): void
    {
        $handle = $this->mutex->tryAcquire($this->key, new LockOptions(Duration::fromSeconds(30)));
        self::assertNotNull($handle);

        $this->clock->advance(Duration::fromSeconds(31));

        $this->expectException(LockLost::class);
        $handle->refresh();
    }

    public function testAcquireThrowsWhenHeldAndNoWaitWindow(): void
    {
        $this->mutex->tryAcquire($this->key, new LockOptions(Duration::fromSeconds(60)));

        $this->expectException(LockNotAcquired::class);
        $this->mutex->acquire($this->key, new LockOptions(Duration::fromSeconds(60)));
    }
}
