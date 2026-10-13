<?php

declare(strict_types=1);

namespace Emorfiq\Tests\Scheduler;

use Emorfiq\Mutex\LockKey;
use Emorfiq\Scheduler\ScheduleRunReport;
use Emorfiq\Scheduler\TaskId;
use Emorfiq\Scheduler\TaskResult;
use Emorfiq\Scheduler\TaskStatus;
use PHPUnit\Framework\TestCase;

final class ScheduleRunReportTest extends TestCase
{
    public function testAllSucceededExitsZero(): void
    {
        $report = ScheduleRunReport::fromResults([
            TaskResult::succeeded(TaskId::fromString('a')),
            TaskResult::skipped(TaskId::fromString('b')),
        ]);

        self::assertSame(0, $report->exitCode());
        self::assertSame([], $report->failures());
    }

    public function testAnyFailureExitsOne(): void
    {
        $report = ScheduleRunReport::fromResults([
            TaskResult::succeeded(TaskId::fromString('a')),
            TaskResult::failed(TaskId::fromString('b'), new \RuntimeException('x')),
        ]);

        self::assertSame(1, $report->exitCode());
        self::assertCount(1, $report->failures());
    }

    public function testTimedOutCountsAsFailure(): void
    {
        $report = ScheduleRunReport::fromResults([
            TaskResult::timedOut(TaskId::fromString('a'), new \RuntimeException('slow')),
        ]);

        self::assertSame(1, $report->exitCode());
    }

    public function testSkippedTickExitsZero(): void
    {
        $report = ScheduleRunReport::skippedTick(LockKey::fromString('scheduler:tick:now'));

        self::assertTrue($report->wasTickSkipped());
        self::assertSame(0, $report->exitCode());
        self::assertSame(0, $report->countByStatus(TaskStatus::Succeeded));
    }
}
