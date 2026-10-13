<?php

declare(strict_types=1);

namespace Emorfiq\Scheduler;

/**
 * How widely a task's run must be de-duplicated.
 *
 *  - None    : no locking. The task is safe to run many times concurrently, or
 *              the cron only ever fires it on one node.
 *  - Server  : at most one run per host at a time (lock key includes hostname).
 *  - Cluster : at most one run across the whole fleet at a time.
 */
enum LockScope
{
    case None;
    case Server;
    case Cluster;
}
