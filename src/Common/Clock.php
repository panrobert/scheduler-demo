<?php

declare(strict_types=1);

namespace Emorfiq\Common;

/**
 * Source of the current instant.
 *
 * The PDF design wrote `Time::now()` as a static facade. Here it is an injected
 * port instead: the scheduler and the mutex both read time through this, so a
 * test can freeze the clock and assert "a task due at 03:00 fires at 03:00" or
 * "a lock whose TTL has passed is free again" without sleeping in real time.
 */
interface Clock
{
    public function now(): \DateTimeImmutable;
}
