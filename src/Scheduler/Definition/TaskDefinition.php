<?php

declare(strict_types=1);

namespace Emorfiq\Scheduler\Definition;

/**
 * What a task actually does, as data.
 *
 * A definition is a value object, not behaviour: it describes the work (a shell
 * command, a console command, a handler class) but does not run it. Execution
 * lives in a TaskExecutor, so the "how to run each kind" logic — process
 * spawning, timeouts, container resolution — stays in one place and the three
 * definition types stay trivial and serialisable.
 */
interface TaskDefinition
{
}
