<?php

declare(strict_types=1);

namespace Emorfiq\Scheduler;

use Emorfiq\Scheduler\Execution\LockingTaskRunner;

/**
 * The default ScheduleEngine. Evaluates due-ness with this package's own
 * CronExpression, then hands each due task to the shared LockingTaskRunner.
 *
 * Swapping to CrunzScheduleEngine changes only how due-ness is decided; locking,
 * execution and the result model are identical because they live in the runner.
 */
final readonly class NativeScheduleEngine implements ScheduleEngine
{
    public function __construct(private LockingTaskRunner $runner)
    {
    }

    public function dispatchDue(Schedule $schedule, \DateTimeImmutable $now): ScheduleRunReport
    {
        $results = [];

        foreach ($schedule as $task) {
            // Evaluate the cron in the task's own timezone, not the server's.
            $localNow = $now->setTimezone($task->timezone);

            if (!$task->expression->isDue($localNow)) {
                continue;
            }

            $results[] = $this->runner->run($task, $now);
        }

        return ScheduleRunReport::fromResults($results);
    }
}
