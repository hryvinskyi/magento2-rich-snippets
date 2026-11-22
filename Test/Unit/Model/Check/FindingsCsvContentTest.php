<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Test\Unit\Model\Check;

use Hryvinskyi\RichSnippets\Model\Check\FindingItem;
use Hryvinskyi\RichSnippets\Model\Check\FindingsCsvContent;
use PHPUnit\Framework\TestCase;

/**
 * The CSV of a finding's pages has a header and a line per page, and no cell a spreadsheet would run as a formula
 */
class FindingsCsvContentTest extends TestCase
{
    public function testEveryPageIsALineUnderTheHeader(): void
    {
        $written = (new FindingsCsvContent())->write([
            [new FindingItem('product', 12, 1, 'Card sleeve', 'LC-CS-BLK', 'Small leather goods')],
            [new FindingItem('home', null, 1, 'Home page')],
        ]);

        self::assertSame(2, $written['rows']);
        self::assertSame(
            "\"Kind of page\",ID,Name,SKU,\"Attribute set\",\"Store view ID\"\n"
            . "product,12,\"Card sleeve\",LC-CS-BLK,\"Small leather goods\",1\n"
            . "home,,\"Home page\",,,1\n",
            $written['content']
        );
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function formulas(): array
    {
        return [
            'equals' => ['=HYPERLINK("https://evil.example")', '\'=HYPERLINK("https://evil.example")'],
            'plus' => ['+1+1', '\'+1+1'],
            'minus' => ['-2+3', '\'-2+3'],
            'at' => ['@SUM(A1)', '\'@SUM(A1)'],
            'tab' => ["\t=1", "'\t=1"],
            'carriage return' => ["\r=1", "'\r=1"],
            'plain text' => ['Card sleeve', 'Card sleeve'],
            'a sign inside' => ['Wax = 100 ml', 'Wax = 100 ml'],
            'empty' => ['', ''],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('formulas')]
    public function testACellThatWouldStartAFormulaIsKeptAsText(string $value, string $cell): void
    {
        self::assertSame($cell, (new FindingsCsvContent())->cell($value));
    }

    public function testANameThatWouldStartAFormulaIsWrittenAsText(): void
    {
        $written = (new FindingsCsvContent())->write([
            [new FindingItem('product', 3, 1, '=cmd|\' /C calc\'!A0', '-LC', '@set')],
        ]);

        self::assertStringContainsString("product,3,\"'=cmd|' /C calc'!A0\",'-LC,'@set,1", $written['content']);
    }
}
