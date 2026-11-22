<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Test\Unit\Model\Wording;

use Hryvinskyi\AdminUi\Model\Page\IcuPluralCategories;
use Hryvinskyi\AdminUi\Model\Page\PluralPhrases;
use Hryvinskyi\RichSnippets\Model\Wording\CountWords;
use Hryvinskyi\RichSnippets\Test\Unit\Fake\AdminTranslator;
use Hryvinskyi\RichSnippets\Test\Unit\Fake\DictionaryRenderer;
use Hryvinskyi\RichSnippets\Test\Unit\Fake\EnglishPlurals;
use Magento\Framework\Phrase;
use Magento\Framework\Phrase\RendererInterface;
use PHPUnit\Framework\TestCase;

/**
 * Numbers of pages and products in the plural form the admin's language gives each number
 */
class CountWordsTest extends TestCase
{
    /**
     * The renderer phrases had before the test
     *
     * @var RendererInterface|null
     */
    private ?RendererInterface $renderer = null;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $this->renderer = Phrase::getRenderer();
    }

    /**
     * @inheritDoc
     */
    protected function tearDown(): void
    {
        if ($this->renderer !== null) {
            Phrase::setRenderer($this->renderer);
        }
    }

    /**
     * English says one page and every other number of pages
     *
     * @return void
     */
    public function testEnglishHasOneAndOther(): void
    {
        Phrase::setRenderer(new DictionaryRenderer([]));
        $counts = new CountWords(new EnglishPlurals());

        self::assertSame(
            ['1 page', '0 pages', '184 pages', '1 product', '23 products'],
            [$counts->pages(1), $counts->pages(0), $counts->pages(184), $counts->products(1), $counts->products(23)]
        );
        self::assertSame(
            ['1 product is invalid', '2 products are invalid', '1 page is invalid', '9 pages are invalid'],
            [
                $counts->invalid(1, true),
                $counts->invalid(2, true),
                $counts->invalid(1, false),
                $counts->invalid(9, false),
            ]
        );
    }

    /**
     * Ukrainian takes its one form for 1 and 21, its few form for 3 and its many form for 5 and 11
     *
     * @return void
     */
    public function testUkrainianTakesTheFormOfEachNumber(): void
    {
        Phrase::setRenderer(new DictionaryRenderer([
            '%1 page' => '%1 сторінка',
            '%1 pages' => '%1 сторінки',
            '%1 pages [plural:few]' => '%1 сторінки',
            '%1 pages [plural:many]' => '%1 сторінок',
        ]));
        $counts = new CountWords(new PluralPhrases(new AdminTranslator('uk_UA'), new IcuPluralCategories()));

        self::assertSame(
            ['1 сторінка', '21 сторінка', '3 сторінки', '5 сторінок', '11 сторінок'],
            [$counts->pages(1), $counts->pages(21), $counts->pages(3), $counts->pages(5), $counts->pages(11)]
        );
    }
}
