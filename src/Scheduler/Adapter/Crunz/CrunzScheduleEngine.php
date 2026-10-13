<?php

declare(strict_types=1);

namespace Emorfiq\Scheduler\Adapter\Crunz;

use Cron\CronExpression as DragonmantankCron;
use Emorfiq\Scheduler\Execution\LockingTaskRunner;
use Emorfiq\Scheduler\Schedule;
use Emorfiq\Scheduler\ScheduledTask;
use Emorfiq\Scheduler\ScheduleEngine;
use Emorfiq\Scheduler\ScheduleRunReport;

/**
 * A second ScheduleEngine behind the same interface, standing in for a crunz
 * integration — this is the whole point of the exercise: the application is not
 * bound to one scheduler library.
 *
 * It delegates cron evaluation to dragonmantank/cron-expression, which is the
 * exact library crunz itself uses internally, so it faithfully represents how a
 * crunz-backed engine would decide due-ness — without pulling crunz's full
 * runtime into this demo. Locking, execution and the result model are unchanged
 * because they come from the shared LockingTaskRunner.
 *
 * If the library is not installed, it degrades to the task's own CronExpression,
 * so the demo runs either way; isAvailable()/usingLibrary() reports which path
 * is active.
 */
final readonly class CrunzScheduleEngine implements ScheduleEngine
{
    public function __construct(private LockingTaskRunner $runner)
    {
    }

    public static function isAvailable(): bool
    {
        return class_exists(DragonmantankCron::class);
    }

    public function usingLibrary(): string
    {
        return self::isAvailable()
            ? 'dragonmantank/cron-expression (crunz-compatible)'
            : 'fallback: native CronExpression';
    }

    public function dispatchDue(Schedule $schedule, \DateTimeImmutable $now): ScheduleRunReport
    {
        $results = [];

        foreach ($schedule as $task) {
            $localNow = $now->setTimezone($task->timezone);

            if (!$this->isDue($task, $localNow)) {
                continue;
            }

            $results[] = $this->runner->run($task, $now);
        }

        return ScheduleRunReport::fromResults($results);
    }

    private function isDue(ScheduledTask $task, \DateTimeImmutable $localNow): bool
    {
        if (self::isAvailable()) {
            return (new DragonmantankCron($task->expression->toString()))->isDue($localNow);
        }

        return $task->expression->isDue($localNow);
    }
}
