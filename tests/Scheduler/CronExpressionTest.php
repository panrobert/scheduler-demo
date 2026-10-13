<?php

declare(strict_types=1);

namespace Emorfiq\Tests\Scheduler;

use Emorfiq\Scheduler\CronExpression;
use Emorfiq\Scheduler\Exception\InvalidCronExpression;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CronExpressionTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string, bool}>
     */
    public static function cases(): iterable
    {
        yield 'every minute' => ['* * * * *', '2026-10-13 12:34:00', true];
        yield 'step matches' => ['*/15 * * * *', '2026-10-13 12:30:00', true];
        yield 'step misses' => ['*/15 * * * *', '2026-10-13 12:31:00', false];
        yield 'exact hour minute' => ['0 3 * * *', '2026-10-13 03:00:00', true];
        yield 'wrong minute' => ['0 3 * * *', '2026-10-13 03:01:00', false];
        yield 'list of minutes' => ['0,30 * * * *', '2026-10-13 12:30:00', true];
        yield 'range of hours' => ['0 9-17 * * *', '2026-10-13 13:00:00', true];
        yield 'range miss' => ['0 9-17 * * *', '2026-10-13 18:00:00', false];
        yield '@daily is midnight' => ['@daily', '2026-10-13 00:00:00', true];
        yield '@daily not midday' => ['@daily', '2026-10-13 12:00:00', false];
        yield 'first of month' => ['0 4 1 * *', '2026-11-01 04:00:00', true];
        yield 'not first of month' => ['0 4 1 * *', '2026-11-02 04:00:00', false];
        // 2026-10-11 is a Sunday.
        yield 'dow sunday via 0' => ['0 0 * * 0', '2026-10-11 00:00:00', true];
        yield 'dow sunday via 7' => ['0 0 * * 7', '2026-10-11 00:00:00', true];
        yield 'dow miss' => ['0 0 * * 1', '2026-10-11 00:00:00', false];
    }

    #[DataProvider('cases')]
    public function testIsDue(string $expression, string $instant, bool $expected): void
    {
        $cron = CronExpression::fromString($expression);
        $now = new \DateTimeImmutable($instant, new \DateTimeZone('UTC'));

        self::assertSame($expected, $cron->isDue($now));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidCases(): iterable
    {
        yield 'empty' => [''];
        yield 'too few fields' => ['* * *'];
        yield 'too many fields' => ['* * * * * *'];
        yield 'minute out of range' => ['60 * * * *'];
        yield 'month out of range' => ['0 0 1 13 *'];
        yield 'reversed range' => ['0 0 * * 5-1'];
        yield 'non numeric' => ['x * * * *'];
    }

    #[DataProvider('invalidCases')]
    public function testInvalidExpressionsThrow(string $expression): void
    {
        $this->expectException(InvalidCronExpression::class);
        CronExpression::fromString($expression);
    }
}
