<?php

declare(strict_types=1);

namespace Emorfiq\Tests\Mutex;

use Emorfiq\Common\Duration;
use Emorfiq\Common\FrozenClock;
use Emorfiq\Mutex\LockKey;
use Emorfiq\Mutex\LockOwner;
use Emorfiq\Mutex\Store\InMemoryLockStore;
use PHPUnit\Framework\TestCase;

final class InMemoryLockStoreTest extends TestCase
{
    private FrozenClock $clock;
    private InMemoryLockStore $store;
    private LockKey $key;

    protected function setUp(): void
    {
        $this->clock = FrozenClock::at('2026-10-13 00:00:00');
        $this->store = new InMemoryLockStore($this->clock);
        $this->key = LockKey::fromString('test:key');
    }

    public function testFirstAcquireSucceedsAndSecondByAnotherOwnerFails(): void
    {
        $alice = LockOwner::generate();
        $bob = LockOwner::generate();

        self::assertTrue($this->store->acquire($this->key, $alice, Duration::fromSeconds(30)));
        self::assertFalse($this->store->acquire($this->key, $bob, Duration::fromSeconds(30)));
    }

    public function testExpiredLockIsFreeAgain(): void
    {
        $alice = LockOwner::generate();
        $bob = LockOwner::generate();

        $this->store->acquire($this->key, $alice, Duration::fromSeconds(30));
        $this->clock->advance(Duration::fromSeconds(31));

        self::assertTrue($this->store->acquire($this->key, $bob, Duration::fromSeconds(30)));
    }

    public function testExtendAndReleaseRequireOwnerMatch(): void
    {
        $alice = LockOwner::generate();
        $bob = LockOwner::generate();
        $this->store->acquire($this->key, $alice, Duration::fromSeconds(30));

        self::assertFalse($this->store->extend($this->key, $bob, Duration::fromSeconds(30)));
        self::assertFalse($this->store->release($this->key, $bob));

        self::assertTrue($this->store->extend($this->key, $alice, Duration::fromSeconds(30)));
        self::assertTrue($this->store->release($this->key, $alice));
    }

    public function testReleaseFreesTheLock(): void
    {
        $alice = LockOwner::generate();
        $bob = LockOwner::generate();

        $this->store->acquire($this->key, $alice, Duration::fromSeconds(30));
        $this->store->release($this->key, $alice);

        self::assertTrue($this->store->acquire($this->key, $bob, Duration::fromSeconds(30)));
    }

    public function testReleaseOnExpiredLockReturnsFalse(): void
    {
        $alice = LockOwner::generate();
        $this->store->acquire($this->key, $alice, Duration::fromSeconds(30));
        $this->clock->advance(Duration::fromSeconds(31));

        self::assertFalse($this->store->release($this->key, $alice));
    }
}
