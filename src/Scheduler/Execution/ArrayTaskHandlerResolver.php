<?php

declare(strict_types=1);

namespace Emorfiq\Scheduler\Execution;

use Emorfiq\Scheduler\Exception\TaskNotFound;
use Emorfiq\Scheduler\TaskHandler;
use Emorfiq\Scheduler\TaskHandlerResolver;

/**
 * A trivial resolver backed by a class-string => handler map. Stands in for a
 * PSR-11 container in the demo and in tests; in a real app you would adapt the
 * framework's container to the TaskHandlerResolver port instead.
 */
final class ArrayTaskHandlerResolver implements TaskHandlerResolver
{
    /**
     * @param array<class-string<TaskHandler>, TaskHandler> $handlers
     */
    public function __construct(private array $handlers = [])
    {
    }

    /**
     * @param class-string<TaskHandler> $handlerClass
     */
    public function register(string $handlerClass, TaskHandler $handler): void
    {
        $this->handlers[$handlerClass] = $handler;
    }

    public function getHandler(string $handlerClass): TaskHandler
    {
        return $this->handlers[$handlerClass] ?? throw TaskNotFound::forHandler($handlerClass);
    }
}
