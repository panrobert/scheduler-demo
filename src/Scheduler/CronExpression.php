<?php

declare(strict_types=1);

namespace Emorfiq\Scheduler;

use Emorfiq\Scheduler\Exception\InvalidCronExpression;

/**
 * A parsed standard 5-field crontab expression (minute hour day-of-month month
 * day-of-week), plus the common @-macros.
 *
 * This is the scheduler's own cron evaluator — the piece the PDF calls out as a
 * genuine library contribution. The CrunzScheduleEngine adapter can replace it
 * wholesale with dragonmantank/cron-expression; the ScheduleEngine interface
 * does not care which one evaluated due-ness.
 *
 * Supported per field: star, a, "a,b", "a-b", "a-b/n", "star/n" (and 7 == Sunday in dow).
 */
final class CronExpression
{
    private const MACROS = [
        '@yearly' => '0 0 1 1 *',
        '@annually' => '0 0 1 1 *',
        '@monthly' => '0 0 1 * *',
        '@weekly' => '0 0 * * 0',
        '@daily' => '0 0 * * *',
        '@midnight' => '0 0 * * *',
        '@hourly' => '0 * * * *',
    ];

    /** [min, max] inclusive bounds for each field. */
    private const BOUNDS = [[0, 59], [0, 23], [1, 31], [1, 12], [0, 6]];

    /**
     * @param array{0: int[], 1: int[], 2: int[], 3: int[], 4: int[]} $fields
     *        The explicit set of allowed values per field, after expansion.
     */
    private function __construct(
        private readonly string $raw,
        private readonly array $fields,
    ) {
    }

    /**
     * @throws InvalidCronExpression
     */
    public static function fromString(string $expression): self
    {
        $trimmed = trim($expression);
        if ($trimmed === '') {
            throw new InvalidCronExpression('Cron expression is empty.');
        }

        $normalized = self::MACROS[strtolower($trimmed)] ?? $trimmed;
        $parts = preg_split('/\s+/', $normalized);

        if ($parts === false || count($parts) !== 5) {
            throw new InvalidCronExpression(sprintf(
                'Expected 5 fields in cron expression "%s", got %d.',
                $expression,
                $parts === false ? 0 : count($parts)
            ));
        }

        $fields = [];
        foreach ($parts as $index => $part) {
            [$min, $max] = self::BOUNDS[$index];
            $fields[$index] = self::parseField($part, $min, $max, $index, $expression);
        }

        /** @var array{0: int[], 1: int[], 2: int[], 3: int[], 4: int[]} $fields */
        return new self($expression, $fields);
    }

    /**
     * True when $now (already expressed in the task's timezone) matches this
     * expression to the minute.
     */
    public function isDue(\DateTimeInterface $now): bool
    {
        $minute = (int) $now->format('i');
        $hour = (int) $now->format('G');
        $dom = (int) $now->format('j');
        $month = (int) $now->format('n');
        $dow = (int) $now->format('w'); // 0 (Sun) .. 6 (Sat)

        if (!in_array($minute, $this->fields[0], true)) {
            return false;
        }
        if (!in_array($hour, $this->fields[1], true)) {
            return false;
        }
        if (!in_array($month, $this->fields[3], true)) {
            return false;
        }

        // Vixie-cron semantics: when BOTH day-of-month and day-of-week are
        // restricted, the task runs if EITHER matches. Otherwise both must match.
        $domRestricted = count($this->fields[2]) !== (self::BOUNDS[2][1] - self::BOUNDS[2][0] + 1);
        $dowRestricted = count($this->fields[4]) !== (self::BOUNDS[4][1] - self::BOUNDS[4][0] + 1);
        $domMatch = in_array($dom, $this->fields[2], true);
        $dowMatch = in_array($dow, $this->fields[4], true);

        if ($domRestricted && $dowRestricted) {
            return $domMatch || $dowMatch;
        }

        return $domMatch && $dowMatch;
    }

    public function toString(): string
    {
        return $this->raw;
    }

    /**
     * @return int[] the sorted, de-duplicated set of allowed values for a field.
     */
    private static function parseField(string $field, int $min, int $max, int $index, string $expression): array
    {
        $allowed = [];

        foreach (explode(',', $field) as $chunk) {
            $step = 1;
            $range = $chunk;

            if (str_contains($chunk, '/')) {
                [$range, $stepStr] = explode('/', $chunk, 2);
                if (!ctype_digit($stepStr) || (int) $stepStr < 1) {
                    throw self::invalid($field, $expression);
                }
                $step = (int) $stepStr;
            }

            if ($range === '*') {
                $lo = $min;
                $hi = $max;
            } elseif (str_contains($range, '-')) {
                [$loStr, $hiStr] = explode('-', $range, 2);
                if (!ctype_digit($loStr) || !ctype_digit($hiStr)) {
                    throw self::invalid($field, $expression);
                }
                $lo = (int) $loStr;
                $hi = (int) $hiStr;
            } else {
                if (!ctype_digit($range)) {
                    throw self::invalid($field, $expression);
                }
                $lo = $hi = (int) $range;
            }

            // Day-of-week allows 7 as an alias for Sunday (0); fold before bounds.
            if ($index === 4) {
                $lo = $lo === 7 ? 0 : $lo;
                $hi = $hi === 7 ? 0 : $hi;
            }

            if ($lo < $min || $hi > $max || $lo > $hi) {
                throw self::invalid($field, $expression);
            }

            for ($value = $lo; $value <= $hi; $value += $step) {
                $allowed[$value] = true;
            }
        }

        $values = array_keys($allowed);
        sort($values);

        return $values;
    }

    private static function invalid(string $field, string $expression): InvalidCronExpression
    {
        return new InvalidCronExpression(sprintf(
            'Invalid cron field "%s" in expression "%s".',
            $field,
            $expression
        ));
    }
}
