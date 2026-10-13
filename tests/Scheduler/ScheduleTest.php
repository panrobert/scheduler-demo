<?php

declare(strict_types=1);

namespace Emorfiq\Tests\Scheduler;

use Emorfiq\Scheduler\CronExpression;
use Emorfiq\Scheduler\Definition\HandlerTask;
use Emorfiq\Scheduler\Exception\DuplicateTaskId;
use Emorfiq\Scheduler\Exception\TaskNotFound;
use Emorfiq\Scheduler\LockPolicy;
use Emorfiq\Scheduler\Schedule;
use Emorfiq\Scheduler\ScheduledTask;
use Emorfiq\Scheduler\TaskId;
use Emorfiq\Tests\Support\SpyHandler;
use PHPUnit\Framework\TestCase;

final class ScheduleTest extends TestCase
{
    public function testFromTasksIndexesAndCounts(): void
    {
        $schedule = Schedule::fromTasks([$this->task('a'), $this->task('b')]);

        self::assertCount(2, $schedule);
        self::assertTrue($schedule->has(TaskId::fromString('a')));
        self::assertSame('b', $schedule->getTask(TaskId::fromString('b'))->id->toString());
    }

    public function testDuplicateIdThrows(): void
    {
        $this->expectException(DuplicateTaskId::class);
        Schedule::fromTasks([$this->task('dup'), $this->task('dup')]);
    }

    public function testGetMissingTaskThrows(): void
    {
        $schedule = Schedule::fromTasks([$this->task('a')]);

        $this->expectException(TaskNotFound::class);
        $schedule->getTask(TaskId::fromString('missing'));
    }

    public function testIsIterable(): void
    {
        $schedule = Schedule::fromTasks([$this->task('a'), $this->task('b')]);

        $ids = [];
        foreach ($schedule as $task) {
            $ids[] = $task->id->toString();
        }

        self::assertSame(['a', 'b'], $ids);
    }

    private function task(string $id): ScheduledTask
    {
        return new ScheduledTask(
            id: TaskId::fromString($id),
            expression: CronExpression::fromString('* * * * *'),
            timezone: new \DateTimeZone('UTC'),
            definition: new HandlerTask(SpyHandler::class),
            lockPolicy: LockPolicy::none(),
        );
    }
}
