<?php

declare(strict_types=1);

namespace Emorfiq\Common;

/**
 * A clock that does not move unless told to.
 *
 * Used by tests and by the CLI `--now=` flag so a tick can be replayed for any
 * instant and the result is deterministic.
 */
final class FrozenClock implements Clock
{
    private \DateTimeImmutable $now;

    public function __construct(\DateTimeImmutable $now)
    {
        $this->now = $now;
    }

    public static function at(string $iso8601, string $timezone = 'UTC'): self
    {
        return new self(new \DateTimeImmutable($iso8601, new \DateTimeZone($timezone)));
    }

    public function now(): \DateTimeImmutable
    {
        return $this->now;
    }

    public function set(\DateTimeImmutable $now): void
    {
        $this->now = $now;
    }

    public function advance(Duration $by): void
    {
        $this->now = $this->now->add(new \DateInterval('PT' . $by->toSeconds() . 'S'));
    }
}
