<?php

declare(strict_types=1);

namespace Emorfiq\Scheduler;

use Emorfiq\Common\Clock;
use Emorfiq\Common\Duration;
use Emorfiq\Mutex\LockKey;
use Emorfiq\Mutex\LockOptions;
use Emorfiq\Mutex\Mutex;

/**
 * Decorates another ScheduleRunner with a per-minute tick lock, so that when the
 * cron fires on every node in a fleet, exactly one node does the work and the
 * rest exit immediately.
 *
 * The lock key is the current minute, so it is naturally unique per tick. The
 * lock is deliberately NEVER released — it is left to expire. Releasing it would
 * let a second, slightly-late node grab the same minute and double-run the tick;
 * letting it expire means the minute stays "taken" until it no longer matters.
 */
final readonly class LockingScheduleRunner implements ScheduleRunner
{
    public function __construct(
        private ScheduleRunner $inner,
        private Mutex $mutex,
        private Clock $clock,
        private Duration $lockTtl,
    ) {
    }

    public function tick(): ScheduleRunReport
    {
        $key = LockKey::fromString(
            'scheduler:tick:' . $this->clock->now()->format('Y-m-d\TH:i')
        );

        if ($this->mutex->tryAcquire($key, new LockOptions($this->lockTtl)) === null) {
            return ScheduleRunReport::skippedTick($key);
        }

        // Note: no release(). The handle falls out of scope and the lock expires
        // with its TTL — see the class docblock for why that is intentional.
        return $this->inner->tick();
    }
}
