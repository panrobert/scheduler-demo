<?php

declare(strict_types=1);

namespace Emorfiq\Scheduler\Definition;

/**
 * Run an application console command in a fresh process, e.g.
 * new ConsoleTask('billing:export-month'). Arguments are passed through
 * untouched and escaped by the executor.
 */
final readonly class ConsoleTask implements TaskDefinition
{
    /** @var list<string> */
    public array $arguments;

    /**
     * @param list<string> $arguments
     */
    public function __construct(public string $command, array $arguments = [])
    {
        if (trim($command) === '') {
            throw new \InvalidArgumentException('ConsoleTask command cannot be empty.');
        }

        $this->arguments = array_values($arguments);
    }
}
