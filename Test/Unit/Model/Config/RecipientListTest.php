<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Test\Unit\Model\Config;

use Hryvinskyi\RichSnippets\Model\Config\RecipientList;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The recipients of the catalog check's findings, read from a comma-separated list
 */
class RecipientListTest extends TestCase
{
    /**
     * @param list<string> $expected
     */
    #[DataProvider('lists')]
    public function testEveryEntryOfAValidListIsAnAddress(string $list, array $expected): void
    {
        self::assertSame($expected, (new RecipientList())->addresses($list));
    }

    /**
     * @return array<string, array{0: string, 1: list<string>}>
     */
    public static function lists(): array
    {
        return [
            'empty' => ['', []],
            'whitespace' => ['  ', []],
            'one' => ['seo@example.com', ['seo@example.com']],
            'several with spaces' => [' seo@example.com ,ops@shop.co.uk', ['seo@example.com', 'ops@shop.co.uk']],
        ];
    }

    #[DataProvider('invalidLists')]
    public function testAListWithAnEntryThatIsNotAnAddressIsRefused(string $list): void
    {
        self::assertNull((new RecipientList())->addresses($list));
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function invalidLists(): array
    {
        return [
            'no top-level domain' => ['seo@linden-co'],
            'no local part' => ['@example.com'],
            'two at signs' => ['seo@@example.com'],
            'inner space' => ['seo team@example.com'],
            'empty entry' => ['seo@example.com,,ops@example.com'],
            'trailing comma' => ['seo@example.com,'],
            'semicolon list' => ['seo@example.com; ops@example.com'],
        ];
    }
}
