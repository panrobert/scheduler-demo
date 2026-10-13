<?php

declare(strict_types=1);

namespace Emorfiq\Common;

/**
 * The real wall clock, always in UTC.
 *
 * Due-ness is evaluated per task in the task's own timezone (see ScheduledTask),
 * so the clock itself stays in UTC and conversion happens at the edge.
 */
final class SystemClock implements Clock
{
    public function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }
}
