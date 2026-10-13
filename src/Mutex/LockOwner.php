<?php

declare(strict_types=1);

namespace Emorfiq\Mutex;

/**
 * @internal Identifies a single acquisition — a fresh token per acquire(), not
 *           per process. It is the answer to property #1 (owner token): only the
 *           holder of this exact token may extend or release the lock.
 *
 * The token never leaves the mutex package. Application code holds a LockHandle;
 * the owner token travels inside it.
 *
 * Format "hostname:pid:random" is a debugging aid only — two processes on the
 * same host in the same second still get different tokens because of the random
 * suffix. Correctness depends on the random part, not on host or pid.
 */
final readonly class LockOwner
{
    private function __construct(private string $token)
    {
    }

    public static function generate(): self
    {
        $host = gethostname() ?: 'unknown-host';
        $pid = getmypid() ?: 0;
        $random = bin2hex(random_bytes(8));

        return new self(sprintf('%s:%d:%s', $host, $pid, $random));
    }

    public static function fromString(string $token): self
    {
        if ($token === '') {
            throw new \InvalidArgumentException('Lock owner token cannot be empty.');
        }

        return new self($token);
    }

    public function toString(): string
    {
        return $this->token;
    }

    public function equals(self $other): bool
    {
        // Constant-time compare: owner tokens act as capabilities, so avoid
        // leaking match progress through timing.
        return hash_equals($this->token, $other->token);
    }
}
