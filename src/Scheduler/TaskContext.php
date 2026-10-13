<?php

declare(strict_types=1);

namespace Emorfiq\Scheduler;

/**
 * What a handler is told about the run it is executing. Passed to
 * TaskHandler::handle(). Deliberately small: a task that needs more should pull
 * it from its own injected collaborators, not from an ever-growing context bag.
 */
final readonly class TaskContext
{
    public function __construct(
        public TaskId $taskId,
        public \DateTimeImmutable $scheduledFor,
    ) {
    }
}
