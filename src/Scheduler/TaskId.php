<?php

declare(strict_types=1);

namespace Emorfiq\Scheduler;

use Emorfiq\Scheduler\Exception\InvalidTaskId;

/**
 * A stable, human-readable task identifier, e.g. "billing.monthly-export" or
 * "reports.send". It is the primary key of a task across the whole system: it
 * names the lock, appears in the run report, and must be unique in a schedule.
 *
 * Validated so an id is safe to embed in a lock key and in log lines.
 */
final readonly class TaskId
{
    private function __construct(private string $value)
    {
    }

    /**
     * @throws InvalidTaskId
     */
    public static function fromString(string $value): self
    {
        // Lowercase dotted/dashed segments: "billing.monthly-export". Keeps ids
        // URL/log/lock-key safe and discourages free-form names that drift.
        if (preg_match('/^[a-z0-9]+([._-][a-z0-9]+)*$/', $value) !== 1) {
            throw new InvalidTaskId(sprintf(
                'Invalid task id "%s": expected lowercase segments separated by . _ or -.',
                $value
            ));
        }

        return new self($value);
    }

    public function toString(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
