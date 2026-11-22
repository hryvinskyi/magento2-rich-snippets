<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Test\Unit\Model\Condition\Sentence;

use Hryvinskyi\RichSnippets\Api\Data\ConditionAggregator;
use Hryvinskyi\RichSnippets\Api\Data\PlacementCountInterface;
use Hryvinskyi\RichSnippets\Model\Placement\KindPhrases;
use Hryvinskyi\RichSnippets\Model\Placement\PlacementCount;
use Hryvinskyi\RichSnippets\Model\Placement\PlacementSentence;
use Hryvinskyi\RichSnippets\Model\Wording\WordList;
use Hryvinskyi\RichSnippets\Test\Unit\Fake\DictionaryRenderer;
use Hryvinskyi\RichSnippets\Test\Unit\Fake\EnglishPlurals;
use Hryvinskyi\RichSnippets\Test\Unit\Fake\PlacementFixture;
use Hryvinskyi\RichSnippets\Test\Unit\Fake\TreeFactoryFake;
use Magento\Framework\Phrase;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Conditions read as the builder's preview reads them, and the whole placement as the Pages section says it under
 * the builder
 */
class ConditionSentenceTest extends TestCase
{
    public function testOneConditionReadsFieldOperatorAndValueLabel(): void
    {
        self::assertSame(
            'Product type is not Configurable',
            PlacementFixture::sentence()->of(PlacementFixture::leaves([['product_type', '!=', 'configurable']]), 1)
        );
    }

    public function testAListReadsEveryValueLabel(): void
    {
        self::assertSame(
            'CMS page is one of FAQ, Shipping & returns',
            PlacementFixture::sentence()->of(PlacementFixture::leaves([['cms_page', '()', 'faq,shipping']]), 1)
        );
    }

    public function testConditionsAreJoinedByTheirGroupsWordAndANestedGroupIsBracketed(): void
    {
        $trees = new TreeFactoryFake();
        $tree = $trees->tree(ConditionAggregator::All, [
            $trees->leaf('product_type', null, '==', 'simple', null),
            $trees->group(ConditionAggregator::Any, [
                $trees->leaf('attribute_set', null, '==', '9', null),
                $trees->leaf('weight', null, '<', '5', null),
            ]),
            $trees->leaf('attribute_set', null, '!=', '', null),
        ]);

        self::assertSame(
            'Product type is Simple and (Attribute set is Gift cards or Weight less than 5)',
            PlacementFixture::sentence()->of($tree, 1)
        );
    }

    public function testNoCompleteConditionReadsAsNothing(): void
    {
        self::assertSame('', PlacementFixture::sentence()->of(PlacementFixture::none(), 1));
        self::assertSame(
            '',
            PlacementFixture::sentence()->of(PlacementFixture::leaves([['product_type', '==', '']]), 1)
        );
    }

    public function testTheProductPagesMinusOneTypeAreSaidAsTheDesignSaysThem(): void
    {
        $words = $this->placement()->describe(
            ['product'],
            PlacementFixture::leaves([['product_type', '!=', 'configurable']]),
            [0],
            ['product' => $this->pages(146, 184)],
            1
        );

        self::assertSame(
            [
                'sentence' => 'Output on product pages where Product type is not Configurable, in every store view.',
                'count' => '146 of 184 product pages match.',
                'countLine' => '146 of 184 product pages match',
                'asked' => null,
            ],
            $words
        );
    }

    public function testTwoCmsPagesInOneStoreViewAreSaidAsTheDesignSaysThem(): void
    {
        $words = $this->placement()->describe(
            ['cms'],
            PlacementFixture::leaves([['cms_page', '()', 'faq,shipping']]),
            [1],
            ['cms' => $this->pages(2, 9)],
            1
        );

        self::assertSame(
            [
                'sentence' => 'Output on CMS pages where CMS page is one of FAQ, Shipping & returns, in English only.',
                'count' => '2 of 9 CMS pages match.',
                'countLine' => '2 of 9 CMS pages match',
                'asked' => null,
            ],
            $words
        );
    }

    public function testSeveralKindsAreSaidWithTheConditionsMetAndTheKindsNoConditionNarrows(): void
    {
        $words = $this->placement()->describe(
            ['cms', 'product'],
            PlacementFixture::leaves([['product_type', '!=', 'configurable']]),
            [0],
            ['product' => $this->pages(146, 184), 'cms' => $this->pages(9, 9)],
            1
        );

        self::assertSame(
            [
                'sentence' => 'Output on product pages and CMS pages, where the conditions are met, '
                    . 'in every store view.',
                'count' => '155 of 193 pages match.',
                'countLine' => '155 of 193 pages match',
                'asked' => 'A condition is asked only of the pages it is about. '
                    . 'CMS pages are not narrowed by these conditions.',
            ],
            $words
        );
    }

    public function testSeveralStoreViewsAreNamedTogetherAsTheDesignJoinsThem(): void
    {
        $words = $this->placement()->describe(['product'], PlacementFixture::leaves([]), [1, 2], [], 1);

        self::assertSame('Output on product pages, in English and Deutsch only.', $words['sentence']);
    }

    public function testAStoreViewTheShopNoLongerHasIsNotNamed(): void
    {
        $words = $this->placement()->describe(['product'], PlacementFixture::leaves([]), [1, 9], [], 1);

        self::assertSame('Output on product pages, in English only.', $words['sentence']);
    }

    /**
     * @return array<string, array{0: list<int>}>
     */
    public static function noStoreView(): array
    {
        return ['none at all' => [[]], 'only store views the shop no longer has' => [[9, 12]]];
    }

    /**
     * @param list<int> $storeIds
     * @return void
     */
    #[DataProvider('noStoreView')]
    public function testASnippetSetForNoExistingStoreViewIsOutputNowhereAndNeverInEveryStoreView(
        array $storeIds
    ): void {
        self::assertSame(
            [
                'sentence' => 'Output nowhere: it is set for no store view.',
                'count' => '',
                'countLine' => '',
                'asked' => null,
            ],
            $this->placement()->describe(['product'], PlacementFixture::leaves([]), $storeIds, [], 1)
        );
    }

    public function testATranslationWordsTheWholeShapeOfTheSentence(): void
    {
        $renderer = Phrase::getRenderer();
        Phrase::setRenderer(new DictionaryRenderer([
            'product pages' => 'сторінки товарів',
            'Output on %1, in %2 only.' => 'Виводиться лише в представленні магазину %2: %1.',
        ]));
        try {
            $words = $this->placement()->describe(
                ['product', 'cms'],
                PlacementFixture::leaves([]),
                [1],
                ['product' => $this->pages(184, 184), 'cms' => $this->pages(9, 9)],
                1
            );
        } finally {
            Phrase::setRenderer($renderer);
        }

        self::assertSame(
            'Виводиться лише в представленні магазину English: сторінки товарів and CMS pages.',
            $words['sentence']
        );
    }

    public function testThePagesOfSeveralKindsAreCountedInThePluralFormOfTheTotal(): void
    {
        $words = $this->placement()->describe(
            ['product', 'cms'],
            PlacementFixture::leaves([['product_type', '!=', 'configurable']]),
            [0],
            ['product' => $this->pages(0, 1), 'cms' => $this->pages(0, 0)],
            1
        );

        self::assertSame('0 of 1 page matches.', $words['count']);
    }

    public function testTheHomePageAloneIsNotNarrowedAndTakesIs(): void
    {
        $words = $this->placement()->describe(
            ['home', 'product'],
            PlacementFixture::leaves([['product_type', '==', 'simple']]),
            [0],
            ['home' => $this->pages(1, 1), 'product' => $this->pages(40, 184)],
            1
        );

        self::assertSame(
            'A condition is asked only of the pages it is about. The home page is not narrowed by these conditions.',
            $words['asked']
        );
    }

    public function testThreeConditionsOrANestedGroupAreSaidAsTheConditionsMet(): void
    {
        $words = $this->placement()->describe(
            ['product'],
            PlacementFixture::leaves([
                ['product_type', '==', 'simple'],
                ['attribute_set', '==', '4'],
                ['weight', '<', '5'],
            ]),
            [0],
            ['product' => $this->pages(12, 184)],
            1
        );

        self::assertSame(
            'Output on product pages, where the conditions are met, in every store view.',
            $words['sentence']
        );
    }

    public function testWithoutConditionsThePagesAreCountedWithoutAMatch(): void
    {
        $words = $this->placement()->describe(
            ['product'],
            PlacementFixture::leaves([['product_type', '==', '']]),
            [0],
            ['product' => $this->pages(184, 184)],
            1
        );

        self::assertSame('Output on product pages, in every store view.', $words['sentence']);
        self::assertSame('184 product pages.', $words['count']);
        self::assertSame('', $words['countLine']);
    }

    public function testNoKindAndEveryOtherPageCountNothing(): void
    {
        self::assertSame(
            ['sentence' => 'Output nowhere yet.', 'count' => '', 'countLine' => '', 'asked' => null],
            $this->placement()->describe([], PlacementFixture::none(), [0], [], 1)
        );
        $other = $this->placement()->describe(['other'], PlacementFixture::none(), [0], [], 1);
        self::assertSame('Output on every other page, in every store view.', $other['sentence']);
        self::assertSame('', $other['count']);
        self::assertSame('', $this->placement()->describe(['home'], PlacementFixture::none(), [0], [
            'home' => $this->pages(1, 1),
        ], 1)['count']);
    }

    /**
     * The placement sentence over the fixture's fields and kinds; store view 1 is English, 2 Deutsch
     *
     * @return PlacementSentence
     */
    private function placement(): PlacementSentence
    {
        $stores = [];
        foreach ([1 => 'English', 2 => 'Deutsch'] as $id => $name) {
            $store = $this->createStub(StoreInterface::class);
            $store->method('getId')->willReturn((string) $id);
            $store->method('getName')->willReturn($name);
            $stores[$id] = $store;
        }
        $storeManager = $this->createStub(StoreManagerInterface::class);
        $storeManager->method('getStores')->willReturn($stores);
        $storeManager->method('getStore')->willReturnCallback(
            static fn (mixed $id): StoreInterface => $stores[is_int($id) ? $id : 0]
                ?? throw new \InvalidArgumentException('No store.')
        );

        return new PlacementSentence(
            PlacementFixture::sentence(),
            PlacementFixture::fields(),
            PlacementFixture::kinds(),
            new KindPhrases([
                'home' => ['phrase' => 'the home page', 'singular' => true],
                'product' => ['phrase' => 'product pages'],
                'category' => ['phrase' => 'category pages'],
                'cms' => ['phrase' => 'CMS pages'],
                'other' => ['phrase' => 'every other page', 'singular' => true],
            ]),
            new WordList(),
            $storeManager,
            new EnglishPlurals()
        );
    }

    /**
     * @param int $matching
     * @param int $total
     * @return PlacementCountInterface
     */
    private function pages(int $matching, int $total): PlacementCountInterface
    {
        return new PlacementCount($matching, $total);
    }
}
