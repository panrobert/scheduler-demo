<?php

declare(strict_types=1);

namespace Emorfiq\Scheduler\Exception;

use Emorfiq\Scheduler\TaskId;

/**
 * Thrown when two tasks claim the same id. Raised at Schedule::fromTasks(), i.e.
 * inside CompositeScheduleProvider — the single point where every module's tasks
 * are merged, so a cross-module id clash is caught once, loudly, at wiring time
 * rather than silently shadowing a task at run time.
 */
final class DuplicateTaskId extends \RuntimeException
{
    public static function forId(TaskId $id): self
    {
        return new self(sprintf('Duplicate task id "%s" in schedule.', $id->toString()));
    }
}
