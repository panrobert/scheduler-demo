<?php

declare(strict_types=1);

namespace Emorfiq\Scheduler\Exception;

use Emorfiq\Scheduler\TaskId;

/**
 * Raised when a process-backed task (shell/console) exits non-zero. Handler
 * tasks fail by throwing their own exception, which the engine catches directly.
 */
final class TaskExecutionFailed extends \RuntimeException
{
    public static function forProcess(TaskId $id, int $exitCode, string $stderr): self
    {
        return new self(sprintf(
            'Task "%s" exited with code %d. %s',
            $id->toString(),
            $exitCode,
            trim($stderr) === '' ? '' : 'stderr: ' . trim($stderr)
        ));
    }
}
