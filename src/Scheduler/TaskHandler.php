<?php

declare(strict_types=1);

namespace Emorfiq\Scheduler;

/**
 * Application-provided task logic. A handler fails by throwing — the engine turns
 * a thrown exception into TaskStatus::Failed and carries on with the remaining
 * due tasks.
 */
interface TaskHandler
{
    public function handle(TaskContext $context): void;
}
