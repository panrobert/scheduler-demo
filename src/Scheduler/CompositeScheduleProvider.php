<?php

declare(strict_types=1);

namespace Emorfiq\Scheduler;

/**
 * Merges every module's provider into one Schedule. This is the single point
 * where cross-module duplicate ids are detected — Schedule::fromTasks() throws
 * DuplicateTaskId here, at wiring time, rather than letting one module silently
 * shadow another's task at run time.
 */
final readonly class CompositeScheduleProvider implements ScheduleProvider
{
    /** @var list<ScheduleProvider> */
    private array $providers;

    /**
     * @param iterable<ScheduleProvider> $providers
     */
    public function __construct(iterable $providers)
    {
        $this->providers = is_array($providers) ? array_values($providers) : iterator_to_array($providers, false);
    }

    public function provide(): Schedule
    {
        $tasks = [];

        foreach ($this->providers as $provider) {
            foreach ($provider->provide() as $task) {
                $tasks[] = $task;
            }
        }

        return Schedule::fromTasks($tasks); // throws DuplicateTaskId on clash.
    }
}
