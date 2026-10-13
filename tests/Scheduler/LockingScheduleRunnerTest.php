<?php

declare(strict_types=1);

namespace Emorfiq\Tests\Scheduler;

use Emorfiq\Common\Duration;
use Emorfiq\Common\FrozenClock;
use Emorfiq\Mutex\Store\InMemoryLockStore;
use Emorfiq\Mutex\StoreMutex;
use Emorfiq\Scheduler\LockingScheduleRunner;
use Emorfiq\Scheduler\ScheduleRunner;
use Emorfiq\Scheduler\ScheduleRunReport;
use Emorfiq\Scheduler\TaskId;
use Emorfiq\Scheduler\TaskResult;
use PHPUnit\Framework\TestCase;

final class LockingScheduleRunnerTest extends TestCase
{
    public function testFirstTickRunsInnerSecondTickOnSameMinuteIsSkipped(): void
    {
        $clock = FrozenClock::at('2026-10-13 03:00:10');
        $mutex = new StoreMutex(new InMemoryLockStore($clock), $clock);

        $innerCalls = 0;
        $inner = new class ($innerCalls) implements ScheduleRunner {
            public function __construct(private int &$calls)
            {
            }

            public function tick(): ScheduleRunReport
            {
                $this->calls++;

                return ScheduleRunReport::fromResults([
                    TaskResult::succeeded(TaskId::fromString('x')),
                ]);
            }
        };

        $runner = new LockingScheduleRunner($inner, $mutex, $clock, Duration::fromSeconds(90));

        $first = $runner->tick();
        self::assertFalse($first->wasTickSkipped());
        self::assertSame(1, $innerCalls);

        // Same store, same minute => tick lock still held => the second node skips.
        $clock->advance(Duration::fromSeconds(5));
        $second = $runner->tick();
        self::assertTrue($second->wasTickSkipped());
        self::assertSame(1, $innerCalls); // inner was NOT called again.
    }

    public function testNextMinuteAcquiresAFreshTickLock(): void
    {
        $clock = FrozenClock::at('2026-10-13 03:00:10');
        $mutex = new StoreMutex(new InMemoryLockStore($clock), $clock);

        $inner = new class implements ScheduleRunner {
            public function tick(): ScheduleRunReport
            {
                return ScheduleRunReport::fromResults([]);
            }
        };

        $runner = new LockingScheduleRunner($inner, $mutex, $clock, Duration::fromSeconds(90));

        self::assertFalse($runner->tick()->wasTickSkipped());

        // A new minute has a different lock key, so it is acquired cleanly.
        $clock->set(new \DateTimeImmutable('2026-10-13 03:01:05', new \DateTimeZone('UTC')));
        self::assertFalse($runner->tick()->wasTickSkipped());
    }
}
