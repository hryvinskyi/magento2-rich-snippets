<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Test\Unit\Model\Config;

use Hryvinskyi\RichSnippets\Model\Config\CheckTimeExpression;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The start time of the nightly check as a daily cron expression
 */
class CheckTimeExpressionTest extends TestCase
{
    #[DataProvider('times')]
    public function testATimeBecomesTheExpressionOfItsMinuteAndHour(string $time, string $expression): void
    {
        self::assertSame($expression, (new CheckTimeExpression())->cronExpression($time));
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function times(): array
    {
        return [
            'the default' => ['03:00', '0 3 * * *'],
            'midnight' => ['00:00', '0 0 * * *'],
            'leading zeros dropped' => ['09:05', '5 9 * * *'],
            'evening' => ['23:30', '30 23 * * *'],
            'last minute' => ['23:59', '59 23 * * *'],
        ];
    }

    #[DataProvider('notTimes')]
    public function testAnythingButHoursAndMinutesHasNoExpression(string $time): void
    {
        self::assertNull((new CheckTimeExpression())->cronExpression($time));
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function notTimes(): array
    {
        return [
            'empty' => [''],
            'one hour digit' => ['3:00'],
            'twelve-hour' => ['3am'],
            'hour 24' => ['24:00'],
            'minute 60' => ['23:60'],
            'seconds' => ['03:00:00'],
            'dot' => ['03.00'],
            'trailing line break' => ["03:00\n"],
        ];
    }
}
