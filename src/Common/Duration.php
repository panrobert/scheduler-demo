<?php

declare(strict_types=1);

namespace Emorfiq\Common;

/**
 * A non-negative span of time, in whole seconds.
 *
 * Used for lock TTLs and task timeouts. Kept intentionally small: the scheduler
 * never needs sub-second precision, and whole seconds map cleanly onto every
 * lock store driver (Redis PX, MySQL TIMESTAMPADD, memcached expiry).
 */
final readonly class Duration
{
    private function __construct(public int $seconds)
    {
        if ($seconds < 0) {
            throw new \InvalidArgumentException(
                sprintf('Duration cannot be negative, got %d seconds.', $seconds)
            );
        }
    }

    public static function fromSeconds(int $seconds): self
    {
        return new self($seconds);
    }

    public static function fromMinutes(int $minutes): self
    {
        return new self($minutes * 60);
    }

    public static function fromHours(int $hours): self
    {
        return new self($hours * 3600);
    }

    public function toSeconds(): int
    {
        return $this->seconds;
    }

    public function equals(self $other): bool
    {
        return $this->seconds === $other->seconds;
    }

    public function __toString(): string
    {
        return $this->seconds . 's';
    }
}
