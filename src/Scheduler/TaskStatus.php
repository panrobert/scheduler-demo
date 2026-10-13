<?php

declare(strict_types=1);

namespace Emorfiq\Scheduler;

/**
 * The outcome of a single task on a single tick.
 *
 *  - Succeeded : ran to completion without throwing.
 *  - Failed    : threw, or a process exited non-zero.
 *  - Skipped   : was due but its lock was held elsewhere — not an error.
 *  - TimedOut  : exceeded its timeout and was stopped.
 */
enum TaskStatus
{
    case Succeeded;
    case Failed;
    case Skipped;
    case TimedOut;

    /** Does this outcome count against the tick's exit code? */
    public function isFailure(): bool
    {
        return $this === self::Failed || $this === self::TimedOut;
    }
}
