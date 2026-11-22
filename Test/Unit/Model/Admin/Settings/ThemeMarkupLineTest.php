<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Test\Unit\Model\Admin\Settings;

use Hryvinskyi\RichSnippets\Api\ThemeMarkupObservationsInterface;
use Hryvinskyi\RichSnippets\Model\Admin\Settings\ThemeMarkupLine;
use Hryvinskyi\RichSnippets\Model\Config\OutputSettings;
use Hryvinskyi\RichSnippets\Model\Storefront\OutputPositionPool;
use Hryvinskyi\RichSnippets\Model\Html\MicrodataItem;
use Hryvinskyi\RichSnippets\Model\Observation\ThemeMarkupObservation;
use Hryvinskyi\RichSnippets\Model\Wording\WordList;
use Hryvinskyi\RichSnippets\Test\Unit\Fake\BuildsCheckWording;
use Hryvinskyi\RichSnippets\Test\Unit\Fake\FixedClock;
use Magento\Framework\App\Config\ScopeConfigInterface;
use PHPUnit\Framework\TestCase;

/**
 * "Found on the Storefront" says the markup is removed, names what the last page test saw and where, or says no page
 * test has read a product page yet
 */
class ThemeMarkupLineTest extends TestCase
{
    use BuildsCheckWording;

    private const NOW = 1795269500;
    private const SEEN = 1795269120;

    public function testRemovedAndNothingSeenSaysEachProductIsDescribedOnce(): void
    {
        self::assertSame(
            [
                'tone' => 'ok',
                'text' => 'Nothing. The theme’s Product markup is removed; each product is described once, '
                    . 'by the Product snippet.',
            ],
            $this->line(true, null)
        );
    }

    public function testMarkupSeenOnTheLastPageReadIsNamedWithWhenAndWhere(): void
    {
        $seen = new ThemeMarkupObservation(1, 'product', '/bags/field-satchel.html', [
            new MicrodataItem('Product', ['name', 'offers', 'aggregateRating']),
        ], self::SEEN);

        self::assertSame(
            [
                'tone' => 'warn',
                'text' => 'Product markup in the theme’s HTML: name, offers and aggregateRating. '
                    . 'Seen today at 13:52 on /bags/field-satchel.html.',
            ],
            $this->line(false, $seen)
        );
    }

    public function testWithoutAPageTestThereIsNothingToTell(): void
    {
        self::assertSame(
            ['tone' => 'none', 'text' => 'No page test has looked at a product page yet.'],
            $this->line(false, null)
        );
    }

    /**
     * The line of store view 1
     *
     * @param bool $removes
     * @param ThemeMarkupObservation|null $observation
     * @return array{tone: string, text: string}
     */
    private function line(bool $removes, ?ThemeMarkupObservation $observation): array
    {
        $config = $this->createMock(ScopeConfigInterface::class);
        $config->method('isSetFlag')->willReturn($removes);
        $observations = $this->createMock(ThemeMarkupObservationsInterface::class);
        $observations->method('latest')->with(1, 'product')->willReturn($observation);

        return (new ThemeMarkupLine(
            new OutputSettings($config, new OutputPositionPool()),
            $observations,
            $this->runTimes(new FixedClock(self::NOW)),
            new WordList()
        ))->forStore(1);
    }
}
