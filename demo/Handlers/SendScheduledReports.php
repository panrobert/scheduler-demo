<?php

declare(strict_types=1);

namespace Emorfiq\Scheduler\Demo\Handlers;

use Emorfiq\Scheduler\TaskContext;
use Emorfiq\Scheduler\TaskHandler;

/**
 * The handler referenced by the PDF's "reports.send" example.
 */
final class SendScheduledReports implements TaskHandler
{
    public function handle(TaskContext $context): void
    {
        fwrite(STDOUT, sprintf(
            "    [reports] delivering nightly reports (scheduled for %s)\n",
            $context->scheduledFor->format('Y-m-d H:i T')
        ));
    }
}
