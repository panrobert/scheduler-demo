<?php

declare(strict_types=1);

namespace Emorfiq\Scheduler;

use Emorfiq\Common\Duration;

/**
 * A task's locking requirement: the scope it must be unique within, and the TTL
 * of the lock that guarantees it.
 *
 * $lockTtl is null only for scope None. For Server/Cluster it is required — the
 * named constructors make it impossible to ask for a lock without a lifetime.
 * Set the TTL to comfortably exceed the task's expected runtime; a task that may
 * run longer should refresh() its handle rather than inflate the TTL.
 */
final readonly class LockPolicy
{
    private function __construct(
        public LockScope $scope,
        public ?Duration $lockTtl,
    ) {
    }

    public static function none(): self
    {
        return new self(LockScope::None, null);
    }

    public static function perServer(Duration $lockTtl): self
    {
        return new self(LockScope::Server, $lockTtl);
    }

    public static function perCluster(Duration $lockTtl): self
    {
        return new self(LockScope::Cluster, $lockTtl);
    }

    public function requiresLock(): bool
    {
        return $this->scope !== LockScope::None;
    }
}
