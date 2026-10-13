<?php

declare(strict_types=1);

namespace Emorfiq\Tests\Support;

use Emorfiq\Scheduler\TaskContext;
use Emorfiq\Scheduler\TaskHandler;

/** Always throws — used to assert failure isolation and Failed results. */
final class BoomHandler implements TaskHandler
{
    public const MESSAGE = 'boom';

    public function handle(TaskContext $context): void
    {
        throw new \RuntimeException(self::MESSAGE);
    }
}
