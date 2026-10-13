<?php

declare(strict_types=1);

namespace Emorfiq\Scheduler\Exception;

use Emorfiq\Common\Duration;
use Emorfiq\Scheduler\TaskId;

/**
 * Raised by a TaskExecutor when a task overruns its timeout. The engine maps it
 * to TaskStatus::TimedOut, distinct from a plain Failed, so operators can tell a
 * slow task from a broken one.
 */
final class TaskTimedOut extends \RuntimeException
{
    public static function forTask(TaskId $id, Duration $timeout): self
    {
        return new self(sprintf(
            'Task "%s" exceeded its timeout of %s.',
            $id->toString(),
            (string) $timeout
        ));
    }
}
