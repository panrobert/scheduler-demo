<?php

declare(strict_types=1);

namespace Emorfiq\Scheduler\Execution;

use Emorfiq\Common\Duration;
use Emorfiq\Scheduler\Definition\ConsoleTask;
use Emorfiq\Scheduler\Definition\HandlerTask;
use Emorfiq\Scheduler\Definition\ShellTask;
use Emorfiq\Scheduler\Exception\TaskExecutionFailed;
use Emorfiq\Scheduler\Exception\TaskTimedOut;
use Emorfiq\Scheduler\ScheduledTask;
use Emorfiq\Scheduler\TaskContext;
use Emorfiq\Scheduler\TaskHandlerResolver;

/**
 * The library's execution contribution: process spawning for shell/console
 * tasks, container resolution for handler tasks, and timeout enforcement.
 *
 * Timeout note: process tasks get a hard timeout (the child is signalled and
 * killed). Handler tasks run in this process, which PHP cannot pre-empt, so for
 * them the timeout is advisory — it is checked after the fact. Long, kill-able
 * work should therefore be a ConsoleTask, not a HandlerTask.
 */
final class NativeTaskExecutor implements TaskExecutor
{
    /**
     * @param list<string> $consoleBinary command prefix for ConsoleTask, e.g.
     *                                     ['php', 'bin/console'] or
     *                                     ['php', 'artisan'].
     */
    public function __construct(
        private readonly TaskHandlerResolver $resolver,
        private readonly array $consoleBinary = ['php', 'bin/console'],
    ) {
    }

    public function execute(ScheduledTask $task, TaskContext $context): void
    {
        $definition = $task->definition;

        if ($definition instanceof HandlerTask) {
            $this->runHandler($definition, $task, $context);
            return;
        }

        if ($definition instanceof ShellTask) {
            $this->runProcess($definition->command, $task); // string => via shell
            return;
        }

        if ($definition instanceof ConsoleTask) {
            $command = array_merge($this->consoleBinary, [$definition->command], $definition->arguments);
            $this->runProcess($command, $task); // array => no shell, auto-escaped
            return;
        }

        throw new \LogicException(sprintf(
            'NativeTaskExecutor cannot run definition of type %s.',
            $definition::class
        ));
    }

    private function runHandler(HandlerTask $definition, ScheduledTask $task, TaskContext $context): void
    {
        $startedAt = microtime(true);

        $this->resolver->getHandler($definition->handlerClass)->handle($context);

        // Advisory timeout: we could not stop it, but we can report it overran.
        if ($task->timeout !== null && (microtime(true) - $startedAt) > $task->timeout->toSeconds()) {
            throw TaskTimedOut::forTask($task->id, $task->timeout);
        }
    }

    /**
     * @param string|list<string> $command
     */
    private function runProcess(string|array $command, ScheduledTask $task): void
    {
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open($command, $descriptors, $pipes);
        if (!is_resource($process)) {
            throw new TaskExecutionFailed(sprintf(
                'Task "%s": could not start process.',
                $task->id->toString()
            ));
        }

        fclose($pipes[0]);
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        $deadline = $task->timeout !== null ? microtime(true) + $task->timeout->toSeconds() : null;
        $stderr = '';

        while (true) {
            $status = proc_get_status($process);
            $stderr .= (string) stream_get_contents($pipes[2]);
            stream_get_contents($pipes[1]); // drain stdout so the child never blocks on a full pipe.

            if (!$status['running']) {
                break;
            }

            if ($deadline !== null && microtime(true) >= $deadline) {
                $this->terminate($process);
                $this->closePipes($pipes, [1, 2]);
                /** @var Duration $timeout */
                $timeout = $task->timeout;
                throw TaskTimedOut::forTask($task->id, $timeout);
            }

            usleep(20_000); // 20ms poll — cheap, and tasks run for seconds, not ms.
        }

        $stderr .= (string) stream_get_contents($pipes[2]);
        $this->closePipes($pipes, [1, 2]);
        $exitCode = proc_close($process);

        if ($exitCode !== 0) {
            throw TaskExecutionFailed::forProcess($task->id, $exitCode, $stderr);
        }
    }

    /**
     * @param resource $process
     */
    private function terminate($process): void
    {
        proc_terminate($process, 15); // SIGTERM
        usleep(200_000);
        if (proc_get_status($process)['running']) {
            proc_terminate($process, 9); // SIGKILL
        }
        proc_close($process);
    }

    /**
     * @param array<int, resource> $pipes
     * @param list<int>            $indexes
     */
    private function closePipes(array $pipes, array $indexes): void
    {
        foreach ($indexes as $i) {
            if (isset($pipes[$i]) && is_resource($pipes[$i])) {
                fclose($pipes[$i]);
            }
        }
    }
}
