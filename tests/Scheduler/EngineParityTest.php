<?php

declare(strict_types=1);

namespace Emorfiq\Tests\Scheduler;

use Emorfiq\Common\FrozenClock;
use Emorfiq\Mutex\Store\InMemoryLockStore;
use Emorfiq\Mutex\StoreMutex;
use Emorfiq\Scheduler\Adapter\Crunz\CrunzScheduleEngine;
use Emorfiq\Scheduler\CronExpression;
use Emorfiq\Scheduler\Definition\HandlerTask;
use Emorfiq\Scheduler\Execution\ArrayTaskHandlerResolver;
use Emorfiq\Scheduler\Execution\LockingTaskRunner;
use Emorfiq\Scheduler\Execution\NativeTaskExecutor;
use Emorfiq\Scheduler\LockPolicy;
use Emorfiq\Scheduler\NativeScheduleEngine;
use Emorfiq\Scheduler\Schedule;
use Emorfiq\Scheduler\ScheduledTask;
use Emorfiq\Scheduler\ScheduleEngine;
use Emorfiq\Scheduler\TaskId;
use Emorfiq\Tests\Support\SpyHandler;
use PHPUnit\Framework\TestCase;

/**
 * The point of the whole exercise: two engines behind one interface must agree.
 * This proves the application is not bound to a single scheduler library.
 */
final class EngineParityTest extends TestCase
{
    public function testNativeAndCrunzEnginesAgreeOnWhatIsDue(): void
    {
        if (!CrunzScheduleEngine::isAvailable()) {
            self::markTestSkipped('dragonmantank/cron-expression not installed.');
        }

        $clock = FrozenClock::at('2026-11-01 04:00:00');
        $schedule = $this->schedule();

        $native = $this->ranTaskIds(new NativeScheduleEngine($this->runner($clock)), $schedule, $clock);
        $crunz = $this->ranTaskIds(new CrunzScheduleEngine($this->runner($clock)), $schedule, $clock);

        self::assertSame(['every-minute', 'first-of-month'], $native);
        self::assertSame($native, $crunz);
    }

    /**
     * @return list<string> the ids that produced a result, in order.
     */
    private function ranTaskIds(ScheduleEngine $engine, Schedule $schedule, FrozenClock $clock): array
    {
        $report = $engine->dispatchDue($schedule, $clock->now());

        return array_map(
            static fn ($r): string => $r->taskId->toString(),
            $report->results
        );
    }

    private function runner(FrozenClock $clock): LockingTaskRunner
    {
        $mutex = new StoreMutex(new InMemoryLockStore($clock), $clock);
        $resolver = new ArrayTaskHandlerResolver([SpyHandler::class => new SpyHandler()]);

        return new LockingTaskRunner($mutex, new NativeTaskExecutor($resolver));
    }

    private function schedule(): Schedule
    {
        return Schedule::fromTasks([
            $this->task('every-minute', '* * * * *'),
            $this->task('first-of-month', '0 4 1 * *'),
            $this->task('nightly', '0 3 * * *'),
        ]);
    }

    private function task(string $id, string $cron): ScheduledTask
    {
        return new ScheduledTask(
            id: TaskId::fromString($id),
            expression: CronExpression::fromString($cron),
            timezone: new \DateTimeZone('UTC'),
            definition: new HandlerTask(SpyHandler::class),
            lockPolicy: LockPolicy::none(),
        );
    }
}
