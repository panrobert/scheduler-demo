<?php

declare(strict_types=1);

namespace Emorfiq\Scheduler;

use Emorfiq\Common\Clock;

/**
 * The core runner: builds the schedule from the provider and asks the engine to
 * dispatch whatever is due right now. No locking of its own — wrap it in a
 * LockingScheduleRunner for the once-per-fleet tick guarantee.
 */
final readonly class DispatchingScheduleRunner implements ScheduleRunner
{
    public function __construct(
        private ScheduleProvider $scheduleProvider,
        private ScheduleEngine $engine,
        private Clock $clock,
    ) {
    }

    public function tick(): ScheduleRunReport
    {
        return $this->engine->dispatchDue(
            $this->scheduleProvider->provide(),
            $this->clock->now()
        );
    }
}
