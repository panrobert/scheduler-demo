<?php

declare(strict_types=1);

namespace Emorfiq\Scheduler;

use Emorfiq\Scheduler\Exception\TaskNotFound;

/**
 * Turns a HandlerTask's class-string into an executable handler, normally via the
 * application's DI container. Isolating this one call is what keeps the scheduler
 * independent of any particular container.
 */
interface TaskHandlerResolver
{
    /**
     * @param class-string<TaskHandler> $handlerClass
     *
     * @throws TaskNotFound when no handler is registered for the class.
     */
    public function getHandler(string $handlerClass): TaskHandler;
}
