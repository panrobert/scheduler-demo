<?php

declare(strict_types=1);

namespace Emorfiq\Mutex;

use Emorfiq\Common\Duration;

/**
 * The parameters of one acquisition, passed to acquire() / tryAcquire().
 *
 * - $ttl is required, with no default: a lock without a lifetime is the exact
 *   failure mode we are designing against (a crashed holder blocking the key
 *   forever). Forcing the caller to state a TTL makes that impossible.
 * - $waitTimeout / $retryInterval only matter when you are willing to wait for
 *   a held lock. The scheduler never waits (it skips instead), so it leaves
 *   both null — tryAcquire() then makes a single attempt.
 */
final readonly class LockOptions
{
    public function __construct(
        public Duration $ttl,
        public ?Duration $waitTimeout = null,
        public ?Duration $retryInterval = null,
    ) {
    }
}
