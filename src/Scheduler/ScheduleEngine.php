<?php

declare(strict_types=1);

namespace Emorfiq\Scheduler;

/**
 * The seam between the application and whatever third-party scheduler library is
 * in use. One interface, one implementation per library (NativeScheduleEngine,
 * CrunzScheduleEngine, ...).
 *
 * dispatchDue() evaluates each task's due-ness against $now — in that task's own
 * timezone — runs the ones that are due, and never throws for a task-level
 * failure: a task that fails becomes a failed TaskResult and the remaining due
 * tasks still run. The only thing that varies between implementations is which
 * library evaluates the cron and spawns the work.
 */
interface ScheduleEngine
{
    public function dispatchDue(Schedule $schedule, \DateTimeImmutable $now): ScheduleRunReport;
}
