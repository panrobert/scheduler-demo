<?php

declare(strict_types=1);

namespace Emorfiq\Scheduler;

use Emorfiq\Scheduler\Exception\DuplicateTaskId;
use Emorfiq\Scheduler\Exception\TaskNotFound;

/**
 * An immutable, uniquely-keyed set of tasks. Built once per tick by a provider
 * and handed to the engine.
 *
 * @implements \IteratorAggregate<string, ScheduledTask>
 */
final readonly class Schedule implements \IteratorAggregate, \Countable
{
    /**
     * @param array<string, ScheduledTask> $tasks keyed by task id string.
     */
    private function __construct(private array $tasks)
    {
    }

    /**
     * @param iterable<ScheduledTask> $tasks
     *
     * @throws DuplicateTaskId when two tasks share an id.
     */
    public static function fromTasks(iterable $tasks): self
    {
        $indexed = [];

        foreach ($tasks as $task) {
            $id = $task->id->toString();
            if (isset($indexed[$id])) {
                throw DuplicateTaskId::forId($task->id);
            }
            $indexed[$id] = $task;
        }

        return new self($indexed);
    }

    /**
     * @throws TaskNotFound
     */
    public function getTask(TaskId $id): ScheduledTask
    {
        return $this->tasks[$id->toString()] ?? throw TaskNotFound::forId($id);
    }

    public function has(TaskId $id): bool
    {
        return isset($this->tasks[$id->toString()]);
    }

    /**
     * @return \Traversable<string, ScheduledTask>
     */
    public function getIterator(): \Traversable
    {
        return new \ArrayIterator($this->tasks);
    }

    public function count(): int
    {
        return count($this->tasks);
    }
}
