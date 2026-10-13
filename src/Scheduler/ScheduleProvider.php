<?php

declare(strict_types=1);

namespace Emorfiq\Scheduler;

/**
 * Supplies the schedule for a module.
 *
 * provide() runs every tick on every node, so it must be cheap: build value
 * objects from constants and config only — no DB, no I/O. Expensive work belongs
 * inside the task, not inside deciding what the tasks are.
 */
interface ScheduleProvider
{
    public function provide(): Schedule;
}
