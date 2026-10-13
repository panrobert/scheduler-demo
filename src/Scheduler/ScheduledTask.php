<?php

declare(strict_types=1);

namespace Emorfiq\Scheduler;

use Emorfiq\Common\Duration;
use Emorfiq\Scheduler\Definition\TaskDefinition;

/**
 * A fully declared task: everything the engine needs to decide whether to run it
 * now, how to isolate it, and how to run it.
 *
 * Pure data, built from constants and config — no DB, no I/O — so a provider can
 * build a whole schedule cheaply on every tick (see ScheduleProvider).
 */
final readonly class ScheduledTask
{
    public function __construct(
        public TaskId $id,
        public CronExpression $expression,
        public \DateTimeZone $timezone,
        public TaskDefinition $definition,
        public LockPolicy $lockPolicy,
        public ?Duration $timeout = null,
        public ?string $description = null,
    ) {
    }
}
