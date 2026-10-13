<?php

declare(strict_types=1);

namespace Emorfiq\Tests\Support;

use Emorfiq\Scheduler\TaskContext;
use Emorfiq\Scheduler\TaskHandler;

/** Records every invocation so tests can assert a task ran (and with what context). */
final class SpyHandler implements TaskHandler
{
    /** @var list<TaskContext> */
    public array $calls = [];

    public function handle(TaskContext $context): void
    {
        $this->calls[] = $context;
    }
}
