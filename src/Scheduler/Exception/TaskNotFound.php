<?php

declare(strict_types=1);

namespace Emorfiq\Scheduler\Exception;

use Emorfiq\Scheduler\TaskId;

final class TaskNotFound extends \RuntimeException
{
    public static function forId(TaskId $id): self
    {
        return new self(sprintf('No task with id "%s" in schedule.', $id->toString()));
    }

    public static function forHandler(string $handlerClass): self
    {
        return new self(sprintf('No handler registered for "%s".', $handlerClass));
    }
}
