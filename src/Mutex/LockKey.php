<?php

declare(strict_types=1);

namespace Emorfiq\Mutex;

use Emorfiq\Mutex\Exception\InvalidLockKey;

/**
 * A namespaced lock identifier, e.g. "scheduler:tick:2026-08-24T03:00" or
 * "scheduler:task:cluster:reports.send".
 *
 * Validated on construction so an invalid key can never reach a store driver,
 * where it might collide with an unrelated key or be silently truncated.
 */
final readonly class LockKey
{
    private const MAX_LENGTH = 191; // fits a utf8mb4 indexed VARCHAR in MySQL.

    private function __construct(private string $value)
    {
    }

    /**
     * @throws InvalidLockKey
     */
    public static function fromString(string $value): self
    {
        if ($value === '') {
            throw new InvalidLockKey('Lock key cannot be empty.');
        }

        if (strlen($value) > self::MAX_LENGTH) {
            throw new InvalidLockKey(sprintf(
                'Lock key cannot exceed %d bytes, got %d.',
                self::MAX_LENGTH,
                strlen($value)
            ));
        }

        // No control characters or whitespace: keys travel into Redis/SQL keys
        // and show up in logs, so they must be a single flat token.
        if (preg_match('/[\s\x00-\x1F\x7F]/', $value) === 1) {
            throw new InvalidLockKey(sprintf(
                'Lock key "%s" must not contain whitespace or control characters.',
                $value
            ));
        }

        return new self($value);
    }

    public function toString(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
