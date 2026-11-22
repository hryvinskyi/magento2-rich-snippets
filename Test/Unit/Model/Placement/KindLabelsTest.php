<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Test\Unit\Model\Placement;

use Hryvinskyi\RichSnippets\Model\Placement\KindLabels;
use PHPUnit\Framework\TestCase;

/**
 * A kind of page is shown by its configured label, the label of one of its pages, glyph and description; a kind
 * configured with none of them is labelled by its code (its pages too), drawn with the glyph of a web page and
 * described by nothing
 */
class KindLabelsTest extends TestCase
{
    public function testAConfiguredKindIsShownAsConfigured(): void
    {
        $labels = $this->labels();

        self::assertSame('Product pages', $labels->label('product'));
        self::assertSame('inventory_2', $labels->icon('product'));
        self::assertSame('', $labels->description('product'));
        self::assertSame('Cart, checkout, account, search', $labels->description('other'));
        self::assertSame('Product page', $labels->pageLabel('product'));
    }

    public function testAKindWithoutAPageLabelNamesItsPagesByItsLabel(): void
    {
        self::assertSame('Every other page', $this->labels()->pageLabel('other'));
    }

    public function testAKindWithoutConfigurationFallsBackToItsCodeAndAWebPage(): void
    {
        $labels = $this->labels();

        self::assertSame('landing', $labels->label('landing'));
        self::assertSame('web', $labels->icon('landing'));
        self::assertSame('', $labels->description('landing'));
        self::assertSame('landing', $labels->pageLabel('landing'));
    }

    public function testAValueOfTheWrongKindCountsAsNone(): void
    {
        $labels = new KindLabels(['odd' => ['label' => 7, 'icon' => ['x']], 'flat' => 'Flat']);

        self::assertSame('odd', $labels->label('odd'));
        self::assertSame('web', $labels->icon('odd'));
        self::assertSame('flat', $labels->label('flat'));
    }

    /**
     * @return KindLabels
     */
    private function labels(): KindLabels
    {
        return new KindLabels([
            'product' => ['label' => 'Product pages', 'page' => 'Product page', 'icon' => 'inventory_2'],
            'other' => [
                'label' => 'Every other page',
                'icon' => 'public',
                'description' => 'Cart, checkout, account, search',
            ],
        ]);
    }
}
