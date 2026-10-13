<?php

declare(strict_types=1);

namespace Emorfiq\Scheduler\Demo;

use Emorfiq\Common\Duration;
use Emorfiq\Scheduler\CronExpression;
use Emorfiq\Scheduler\Definition\ConsoleTask;
use Emorfiq\Scheduler\Definition\HandlerTask;
use Emorfiq\Scheduler\Definition\ShellTask;
use Emorfiq\Scheduler\Demo\Handlers\FlakyHandler;
use Emorfiq\Scheduler\Demo\Handlers\HeartbeatHandler;
use Emorfiq\Scheduler\Demo\Handlers\SendScheduledReports;
use Emorfiq\Scheduler\LockPolicy;
use Emorfiq\Scheduler\Schedule;
use Emorfiq\Scheduler\ScheduledTask;
use Emorfiq\Scheduler\ScheduleProvider;
use Emorfiq\Scheduler\TaskId;

/**
 * The demo schedule. Mirrors the PDF's examples and adds an every-minute
 * heartbeat (so a live `tick` always shows activity) and a deliberately failing
 * task (so you can see a Failed result).
 */
final class DemoScheduleProvider implements ScheduleProvider
{
    public function provide(): Schedule
    {
        return Schedule::fromTasks([
            // Always due — makes a live `tick` do something visible.
            new ScheduledTask(
                id: TaskId::fromString('demo.heartbeat'),
                expression: CronExpression::fromString('* * * * *'),
                timezone: new \DateTimeZone('UTC'),
                definition: new HandlerTask(HeartbeatHandler::class),
                lockPolicy: LockPolicy::none(),
                description: 'Every-minute heartbeat',
            ),

            // From the PDF: nightly report delivery, cluster-locked.
            new ScheduledTask(
                id: TaskId::fromString('reports.send'),
                expression: CronExpression::fromString('0 3 * * *'),
                timezone: new \DateTimeZone('Europe/Prague'),
                definition: new HandlerTask(SendScheduledReports::class),
                lockPolicy: LockPolicy::perCluster(Duration::fromMinutes(30)),
                description: 'Nightly report delivery',
            ),

            // From the PDF: daily logrotate via a shell command, no lock.
            new ScheduledTask(
                id: TaskId::fromString('logs.rotate'),
                expression: CronExpression::fromString('@daily'),
                timezone: new \DateTimeZone('UTC'),
                definition: new ShellTask('/usr/sbin/logrotate --version'),
                lockPolicy: LockPolicy::none(),
                timeout: Duration::fromMinutes(2),
                description: 'Rotate logs',
            ),

            // From the PDF: monthly billing export via a console command.
            new ScheduledTask(
                id: TaskId::fromString('billing.monthly-export'),
                expression: CronExpression::fromString('0 4 1 * *'),
                timezone: new \DateTimeZone('UTC'),
                definition: new ConsoleTask('billing:export-month'),
                lockPolicy: LockPolicy::perCluster(Duration::fromHours(4)),
                timeout: Duration::fromHours(3),
                description: 'Monthly billing export, 4am on the 1st',
            ),

            // Always due + always fails — shows failure isolation + exit code 1.
            new ScheduledTask(
                id: TaskId::fromString('demo.flaky'),
                expression: CronExpression::fromString('* * * * *'),
                timezone: new \DateTimeZone('UTC'),
                definition: new HandlerTask(FlakyHandler::class),
                lockPolicy: LockPolicy::none(),
                description: 'Deliberately failing task',
            ),
        ]);
    }
}
