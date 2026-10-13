<?php

declare(strict_types=1);

namespace Emorfiq\Scheduler;

use Emorfiq\Mutex\LockKey;

/**
 * The outcome of one tick: either the tick lock was held elsewhere (the whole
 * tick was skipped), or a list of per-task results.
 *
 * This is the application-owned result model — the scheduler library never
 * decides what an exit code means; this class does.
 */
final readonly class ScheduleRunReport
{
    /**
     * @param list<TaskResult> $results
     */
    private function __construct(
        public array $results,
        public bool $tickSkipped,
        public ?LockKey $tickLockKey = null,
    ) {
    }

    /**
     * @param list<TaskResult> $results
     */
    public static function fromResults(array $results): self
    {
        return new self(array_values($results), false, null);
    }

    /**
     * The tick lock was held by another node, so this node did nothing. Not an
     * error — exactly one node does the work and the rest skip.
     */
    public static function skippedTick(LockKey $key): self
    {
        return new self([], true, $key);
    }

    public function wasTickSkipped(): bool
    {
        return $this->tickSkipped;
    }

    /**
     * @return list<TaskResult>
     */
    public function failures(): array
    {
        return array_values(array_filter(
            $this->results,
            static fn (TaskResult $r): bool => $r->status->isFailure()
        ));
    }

    public function countByStatus(TaskStatus $status): int
    {
        return count(array_filter(
            $this->results,
            static fn (TaskResult $r): bool => $r->status === $status
        ));
    }

    /**
     * CLI exit code: 0 when the tick was skipped or every task was fine;
     * 1 when at least one task Failed or TimedOut. A locked task (Skipped) is
     * not a failure.
     */
    public function exitCode(): int
    {
        if ($this->tickSkipped) {
            return 0;
        }

        return $this->failures() === [] ? 0 : 1;
    }
}
