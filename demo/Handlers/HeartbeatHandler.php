<?php

declare(strict_types=1);

namespace Emorfiq\Scheduler\Demo\Handlers;

use Emorfiq\Scheduler\TaskContext;
use Emorfiq\Scheduler\TaskHandler;

/**
 * Trivial handler that prints a line, so a live `tick` visibly does something.
 */
final class HeartbeatHandler implements TaskHandler
{
    public function handle(TaskContext $context): void
    {
        fwrite(STDOUT, sprintf(
            "    [heartbeat] %s ran for %s\n",
            $context->taskId->toString(),
            $context->scheduledFor->format('Y-m-d H:i:s T')
        ));
    }
}
