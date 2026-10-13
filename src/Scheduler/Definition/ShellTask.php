<?php

declare(strict_types=1);

namespace Emorfiq\Scheduler\Definition;

/**
 * Run a raw shell command, e.g. new ShellTask('/usr/sbin/logrotate').
 */
final readonly class ShellTask implements TaskDefinition
{
    public function __construct(public string $command)
    {
        if (trim($command) === '') {
            throw new \InvalidArgumentException('ShellTask command cannot be empty.');
        }
    }
}
