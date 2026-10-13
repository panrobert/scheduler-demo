<?php

declare(strict_types=1);

namespace Emorfiq\Scheduler\Definition;

use Emorfiq\Scheduler\TaskHandler;

/**
 * Run an in-process handler resolved from the container, e.g.
 * new HandlerTask(SendScheduledReports::class).
 *
 * Unlike shell/console tasks this runs in the scheduler's own process, so it
 * shares the request lifecycle and DI container — cheaper, but a timeout can
 * only be advisory (PHP cannot pre-empt its own call stack).
 */
final readonly class HandlerTask implements TaskDefinition
{
    /**
     * @param class-string<TaskHandler> $handlerClass
     */
    public function __construct(public string $handlerClass)
    {
        if (!is_subclass_of($handlerClass, TaskHandler::class)) {
            throw new \InvalidArgumentException(sprintf(
                '%s must implement %s.',
                $handlerClass,
                TaskHandler::class
            ));
        }
    }
}
