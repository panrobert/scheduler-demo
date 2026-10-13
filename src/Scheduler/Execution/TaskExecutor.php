<?php

declare(strict_types=1);

namespace Emorfiq\Scheduler\Execution;

use Emorfiq\Scheduler\Exception\TaskExecutionFailed;
use Emorfiq\Scheduler\Exception\TaskTimedOut;
use Emorfiq\Scheduler\ScheduledTask;
use Emorfiq\Scheduler\TaskContext;

/**
 * Runs the work described by a task's definition. Separated from the engine so
 * that "decide what is due + lock it" (engine) and "actually run this shell /
 * console / handler task, with timeout" (executor) are independent concerns.
 *
 * Fails by throwing: a clean return means success.
 */
interface TaskExecutor
{
    /**
     * @throws TaskTimedOut        when the task overran ScheduledTask::$timeout
     * @throws TaskExecutionFailed when a process task exited non-zero
     * @throws \Throwable          whatever a handler task threw
     */
    public function execute(ScheduledTask $task, TaskContext $context): void;
}
