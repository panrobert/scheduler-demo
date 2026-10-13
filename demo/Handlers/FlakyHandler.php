<?php

declare(strict_types=1);

namespace Emorfiq\Scheduler\Demo\Handlers;

use Emorfiq\Scheduler\TaskContext;
use Emorfiq\Scheduler\TaskHandler;

/**
 * Always throws — used to show that one failing task becomes a Failed TaskResult
 * without aborting the rest of the tick.
 */
final class FlakyHandler implements TaskHandler
{
    public function handle(TaskContext $context): void
    {
        throw new \RuntimeException('Simulated failure in ' . $context->taskId->toString());
    }
}
