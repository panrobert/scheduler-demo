<?php

declare(strict_types=1);

namespace Emorfiq\Scheduler\Execution;

use Emorfiq\Mutex\LockKey;
use Emorfiq\Mutex\LockOptions;
use Emorfiq\Mutex\Mutex;
use Emorfiq\Scheduler\Exception\TaskTimedOut;
use Emorfiq\Scheduler\LockScope;
use Emorfiq\Scheduler\ScheduledTask;
use Emorfiq\Scheduler\TaskContext;
use Emorfiq\Scheduler\TaskResult;

/**
 * Runs a single already-due task: takes its per-task lock (if any), executes it,
 * maps the outcome to a TaskResult, and always releases the lock.
 *
 * This is the part every ScheduleEngine shares — only the "is it due?" decision
 * differs between the native engine and the crunz adapter. Keeping it here means
 * locking, timeout mapping and error handling have exactly one implementation.
 */
final readonly class LockingTaskRunner
{
    public function __construct(
        private Mutex $mutex,
        private TaskExecutor $executor,
    ) {
    }

    public function run(ScheduledTask $task, \DateTimeImmutable $now): TaskResult
    {
        if (!$task->lockPolicy->requiresLock()) {
            return $this->execute($task, $now);
        }

        $key = $this->lockKeyFor($task);
        /** @var \Emorfiq\Common\Duration $ttl */
        $ttl = $task->lockPolicy->lockTtl;

        // tryAcquire, not acquire: a held task lock means "another node is already
        // running this one" — we record Skipped and move on, we do not wait.
        $handle = $this->mutex->tryAcquire($key, new LockOptions($ttl));
        if ($handle === null) {
            return TaskResult::skipped($task->id);
        }

        try {
            return $this->execute($task, $now);
        } finally {
            $handle->release();
        }
    }

    private function execute(ScheduledTask $task, \DateTimeImmutable $now): TaskResult
    {
        $context = new TaskContext($task->id, $now);

        try {
            $this->executor->execute($task, $context);

            return TaskResult::succeeded($task->id);
        } catch (TaskTimedOut $e) {
            return TaskResult::timedOut($task->id, $e);
        } catch (\Throwable $e) {
            // Never rethrow: one broken task must not stop the rest of the tick.
            return TaskResult::failed($task->id, $e);
        }
    }

    private function lockKeyFor(ScheduledTask $task): LockKey
    {
        $id = $task->id->toString();

        $name = match ($task->lockPolicy->scope) {
            LockScope::Cluster => sprintf('scheduler:task:cluster:%s', $id),
            LockScope::Server => sprintf('scheduler:task:server:%s:%s', $this->hostname(), $id),
            LockScope::None => throw new \LogicException('lockKeyFor called for an unlocked task.'),
        };

        return LockKey::fromString($name);
    }

    private function hostname(): string
    {
        return gethostname() ?: 'unknown-host';
    }
}
