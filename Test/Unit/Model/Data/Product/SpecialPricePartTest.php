<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Test\Unit\Model\Data\Product;

use Hryvinskyi\RichSnippets\Model\Catalog\EavValueReader;
use Hryvinskyi\RichSnippets\Model\Data\Product\SpecialPricePart;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use PHPUnit\Framework\TestCase;

/**
 * The special price's last day is given only while the special price is active today
 */
class SpecialPricePartTest extends TestCase
{
    public function testTheLastDayIsGivenOnlyWhileTheSpecialPriceIsActiveToday(): void
    {
        $reader = $this->createMock(EavValueReader::class);
        $reader->method('values')->willReturn([
            1 => [
                'special_price' => '96',
                'special_from_date' => '2026-11-01 00:00:00',
                'special_to_date' => '2026-11-30 00:00:00',
            ],
            2 => ['special_price' => '96', 'special_to_date' => '2026-11-20 00:00:00'],
            3 => [
                'special_price' => '96',
                'special_from_date' => '2026-11-22 00:00:00',
                'special_to_date' => '2026-11-30 00:00:00',
            ],
            4 => ['special_to_date' => '2026-11-30 00:00:00'],
            5 => ['special_price' => '96'],
            6 => ['special_price' => '96', 'special_to_date' => '2026-11-21 00:00:00'],
        ]);
        $timezone = $this->createMock(TimezoneInterface::class);
        $timezone->method('scopeDate')->willReturn(new \DateTime('2026-11-21 10:00:00'));

        $entries = (new SpecialPricePart($reader, $timezone))->load([1, 2, 3, 4, 5, 6], 1, [], []);

        self::assertSame(['special_to_date' => '2026-11-30'], $entries[1]);
        self::assertSame(['special_to_date' => '2026-11-21'], $entries[6], 'The last day itself is still active.');
        self::assertArrayNotHasKey(2, $entries, 'Ended yesterday.');
        self::assertArrayNotHasKey(3, $entries, 'Starts tomorrow.');
        self::assertArrayNotHasKey(4, $entries, 'No special price.');
        self::assertArrayNotHasKey(5, $entries, 'No last day.');
    }
}
