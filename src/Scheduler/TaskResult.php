<?php

declare(strict_types=1);

namespace Emorfiq\Scheduler;

/**
 * The result of running one task. Named constructors keep call sites readable and
 * guarantee the error is present exactly when the status implies one.
 */
final readonly class TaskResult
{
    public function __construct(
        public TaskId $taskId,
        public TaskStatus $status,
        public ?\Throwable $error = null,
    ) {
    }

    public static function succeeded(TaskId $taskId): self
    {
        return new self($taskId, TaskStatus::Succeeded);
    }

    public static function failed(TaskId $taskId, \Throwable $error): self
    {
        return new self($taskId, TaskStatus::Failed, $error);
    }

    public static function skipped(TaskId $taskId): self
    {
        return new self($taskId, TaskStatus::Skipped);
    }

    public static function timedOut(TaskId $taskId, \Throwable $error): self
    {
        return new self($taskId, TaskStatus::TimedOut, $error);
    }
}
