<?php

declare(strict_types=1);

namespace Emorfiq\Scheduler;

/**
 * The scheduler's entry point: run every task due at this instant, then return.
 *
 * Invoked once a minute from the cron adapter:
 *   * * * * *  php bin/scheduler tick
 *
 * Never throws for task-level failures — those are reported inside the
 * ScheduleRunReport (and surface as the CLI exit code).
 */
interface ScheduleRunner
{
    public function tick(): ScheduleRunReport;
}
