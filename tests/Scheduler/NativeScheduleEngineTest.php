<?php

declare(strict_types=1);

namespace Emorfiq\Tests\Scheduler;

use Emorfiq\Common\Duration;
use Emorfiq\Common\FrozenClock;
use Emorfiq\Mutex\LockKey;
use Emorfiq\Mutex\LockOptions;
use Emorfiq\Mutex\Store\InMemoryLockStore;
use Emorfiq\Mutex\StoreMutex;
use Emorfiq\Scheduler\CronExpression;
use Emorfiq\Scheduler\Definition\HandlerTask;
use Emorfiq\Scheduler\Execution\ArrayTaskHandlerResolver;
use Emorfiq\Scheduler\Execution\LockingTaskRunner;
use Emorfiq\Scheduler\Execution\NativeTaskExecutor;
use Emorfiq\Scheduler\LockPolicy;
use Emorfiq\Scheduler\NativeScheduleEngine;
use Emorfiq\Scheduler\Schedule;
use Emorfiq\Scheduler\ScheduledTask;
use Emorfiq\Scheduler\TaskId;
use Emorfiq\Scheduler\TaskStatus;
use Emorfiq\Tests\Support\BoomHandler;
use Emorfiq\Tests\Support\SpyHandler;
use PHPUnit\Framework\TestCase;

final class NativeScheduleEngineTest extends TestCase
{
    private FrozenClock $clock;
    private StoreMutex $mutex;
    private SpyHandler $spy;
    private NativeScheduleEngine $engine;

    protected function setUp(): void
    {
        $this->clock = FrozenClock::at('2026-10-13 03:00:00');
        $this->mutex = new StoreMutex(new InMemoryLockStore($this->clock), $this->clock);
        $this->spy = new SpyHandler();

        $resolver = new ArrayTaskHandlerResolver([
            SpyHandler::class => $this->spy,
            BoomHandler::class => new BoomHandler(),
        ]);
        $executor = new NativeTaskExecutor($resolver);
        $this->engine = new NativeScheduleEngine(new LockingTaskRunner($this->mutex, $executor));
    }

    public function testDueTaskRunsAndSucceeds(): void
    {
        $schedule = Schedule::fromTasks([
            $this->handlerTask('due', '0 3 * * *', SpyHandler::class),
        ]);

        $report = $this->engine->dispatchDue($schedule, $this->clock->now());

        self::assertCount(1, $this->spy->calls);
        self::assertSame(TaskStatus::Succeeded, $report->results[0]->status);
    }

    public function testNotDueTaskProducesNoResult(): void
    {
        $schedule = Schedule::fromTasks([
            $this->handlerTask('later', '0 4 * * *', SpyHandler::class),
        ]);

        $report = $this->engine->dispatchDue($schedule, $this->clock->now());

        self::assertSame([], $report->results);
        self::assertSame([], $this->spy->calls);
    }

    public function testOneFailingTaskDoesNotStopTheRest(): void
    {
        $schedule = Schedule::fromTasks([
            $this->handlerTask('boom', '0 3 * * *', BoomHandler::class),
            $this->handlerTask('ok', '0 3 * * *', SpyHandler::class),
        ]);

        $report = $this->engine->dispatchDue($schedule, $this->clock->now());

        self::assertSame(TaskStatus::Failed, $report->results[0]->status);
        self::assertSame(BoomHandler::MESSAGE, $report->results[0]->error?->getMessage());
        self::assertSame(TaskStatus::Succeeded, $report->results[1]->status);
        self::assertCount(1, $this->spy->calls); // the OK task still ran.
    }

    public function testHeldTaskLockProducesSkipped(): void
    {
        // Simulate another node already running the task by taking its lock first.
        $key = LockKey::fromString('scheduler:task:cluster:locked');
        self::assertNotNull($this->mutex->tryAcquire($key, new LockOptions(Duration::fromMinutes(10))));

        $schedule = Schedule::fromTasks([
            new ScheduledTask(
                id: TaskId::fromString('locked'),
                expression: CronExpression::fromString('0 3 * * *'),
                timezone: new \DateTimeZone('UTC'),
                definition: new HandlerTask(SpyHandler::class),
                lockPolicy: LockPolicy::perCluster(Duration::fromMinutes(10)),
            ),
        ]);

        $report = $this->engine->dispatchDue($schedule, $this->clock->now());

        self::assertSame(TaskStatus::Skipped, $report->results[0]->status);
        self::assertSame([], $this->spy->calls); // never ran.
    }

    public function testDuenessUsesTaskTimezone(): void
    {
        // 03:00 Europe/Prague == 01:00 UTC on this date (CEST, +2).
        $this->clock->set(new \DateTimeImmutable('2026-10-13 01:00:00', new \DateTimeZone('UTC')));

        $schedule = Schedule::fromTasks([
            new ScheduledTask(
                id: TaskId::fromString('prague'),
                expression: CronExpression::fromString('0 3 * * *'),
                timezone: new \DateTimeZone('Europe/Prague'),
                definition: new HandlerTask(SpyHandler::class),
                lockPolicy: LockPolicy::none(),
            ),
        ]);

        $report = $this->engine->dispatchDue($schedule, $this->clock->now());

        self::assertSame(TaskStatus::Succeeded, $report->results[0]->status);
        self::assertCount(1, $this->spy->calls);
    }

    /**
     * @param class-string<\Emorfiq\Scheduler\TaskHandler> $handlerClass
     */
    private function handlerTask(string $id, string $cron, string $handlerClass): ScheduledTask
    {
        return new ScheduledTask(
            id: TaskId::fromString($id),
            expression: CronExpression::fromString($cron),
            timezone: new \DateTimeZone('UTC'),
            definition: new HandlerTask($handlerClass),
            lockPolicy: LockPolicy::none(),
        );
    }
}
