<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Test\Unit\Model\Admin\Test\Reason;

use Hryvinskyi\RichSnippets\Api\Data\ConditionTreeInterface;
use Hryvinskyi\RichSnippets\Api\Data\ExclusionReason;
use Hryvinskyi\RichSnippets\Api\Data\SnippetExclusionInterface;
use Hryvinskyi\RichSnippets\Model\Admin\Test\Reason\ConditionsNotMetWording;
use Hryvinskyi\RichSnippets\Model\Admin\Test\Reason\ExclusionWordingInterface;
use Hryvinskyi\RichSnippets\Model\Admin\Test\Reason\ExclusionWordings;
use Hryvinskyi\RichSnippets\Model\Admin\Test\Reason\NoPageKindsWording;
use Hryvinskyi\RichSnippets\Model\Admin\Test\Reason\OtherKindsWording;
use Hryvinskyi\RichSnippets\Model\Admin\Test\Reason\OtherStoresWording;
use Hryvinskyi\RichSnippets\Model\Admin\Test\Reason\SwitchedOffWording;
use Hryvinskyi\RichSnippets\Model\Condition\Tree\ConditionLeaf;
use Hryvinskyi\RichSnippets\Model\Placement\ConditionMismatch;
use Hryvinskyi\RichSnippets\Model\Placement\KindPhrases;
use Hryvinskyi\RichSnippets\Model\Selection\SnippetExclusion;
use Hryvinskyi\RichSnippets\Model\Snippet;
use Hryvinskyi\RichSnippets\Model\Wording\WordList;
use Hryvinskyi\RichSnippets\Test\Unit\Fake\PlacementFixture;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Each reason a page snippet is not output on a tested page is worded by its own wording, picked by the reason's
 * value; a reason without one fails loudly
 */
class ExclusionWordingsTest extends TestCase
{
    /**
     * The wordings as the module configures them, with the module's kind phrases
     *
     * @return ExclusionWordings
     */
    public static function wordings(): ExclusionWordings
    {
        return new ExclusionWordings([
            ExclusionReason::SwitchedOff->value => new SwitchedOffWording(),
            ExclusionReason::OtherStores->value => new OtherStoresWording(),
            ExclusionReason::NoPageKinds->value => new NoPageKindsWording(),
            ExclusionReason::OtherKinds->value => new OtherKindsWording(new WordList(), new KindPhrases([
                'home' => ['phrase' => 'the home page', 'singular' => true],
                'product' => ['phrase' => 'product pages'],
                'category' => ['phrase' => 'category pages'],
                'cms' => ['phrase' => 'CMS pages'],
                'other' => ['phrase' => 'every other page', 'singular' => true],
            ])),
            ExclusionReason::ConditionsNotMet->value => new ConditionsNotMetWording(PlacementFixture::sentence()),
        ]);
    }

    /**
     * @return array<string,array{ExclusionWordingInterface,SnippetExclusionInterface,string}>
     */
    public static function reasons(): array
    {
        $kinds = new OtherKindsWording(new WordList(), new KindPhrases([
            'product' => ['phrase' => 'product pages'],
            'cms' => ['phrase' => 'CMS pages'],
        ]));
        $configurable = PlacementFixture::leaves([['product_type', '==', 'configurable']]);

        return [
            'switched off' => [
                new SwitchedOffWording(),
                new SnippetExclusion(self::snippet(['product']), ExclusionReason::SwitchedOff),
                'Switched off.',
            ],
            'other store views' => [
                new OtherStoresWording(),
                new SnippetExclusion(self::snippet(['product']), ExclusionReason::OtherStores),
                'Set for other store views.',
            ],
            'no kind of page' => [
                new NoPageKindsWording(),
                new SnippetExclusion(self::snippet([]), ExclusionReason::NoPageKinds),
                'Set for no kind of page yet.',
            ],
            'other kinds of page, by their phrases' => [
                $kinds,
                new SnippetExclusion(self::snippet(['product', 'cms']), ExclusionReason::OtherKinds),
                'Set for product pages and CMS pages.',
            ],
            'one condition, with the page\'s own value' => [
                new ConditionsNotMetWording(PlacementFixture::sentence()),
                new SnippetExclusion(
                    self::snippet(['product'], $configurable),
                    ExclusionReason::ConditionsNotMet,
                    [self::mismatch('Simple')]
                ),
                'Set for product type Configurable; this one is Simple.',
            ],
            'one condition, the page having no value of its own' => [
                new ConditionsNotMetWording(PlacementFixture::sentence()),
                new SnippetExclusion(
                    self::snippet(['product'], $configurable),
                    ExclusionReason::ConditionsNotMet,
                    [self::mismatch(null)]
                ),
                'Set for product type Configurable only.',
            ],
        ];
    }

    #[DataProvider('reasons')]
    public function testEachWordingSaysWhyInItsOwnWords(
        ExclusionWordingInterface $wording,
        SnippetExclusionInterface $exclusion,
        string $expected
    ): void {
        self::assertSame($expected, $wording->text($exclusion, 1));
    }

    public function testSeveralConditionsAreQuotedWhole(): void
    {
        $conditions = PlacementFixture::leaves([
            ['product_type', '==', 'configurable'],
            ['product_type', '!=', 'simple'],
        ]);
        $exclusion = new SnippetExclusion(
            self::snippet(['product'], $conditions),
            ExclusionReason::ConditionsNotMet,
            [self::mismatch('Simple')]
        );

        $text = (new ConditionsNotMetWording(PlacementFixture::sentence()))->text($exclusion, 1);

        self::assertSame(
            sprintf('The conditions in Pages ask for “%s”.', PlacementFixture::sentence()->of($conditions, 1)),
            $text
        );
    }

    /**
     * @return array<string,array{ExclusionReason,string}>
     */
    public static function everyReason(): array
    {
        return [
            'switched off' => [ExclusionReason::SwitchedOff, 'Switched off.'],
            'other store views' => [ExclusionReason::OtherStores, 'Set for other store views.'],
            'no kind of page' => [ExclusionReason::NoPageKinds, 'Set for no kind of page yet.'],
            'other kinds' => [ExclusionReason::OtherKinds, 'Set for CMS pages.'],
        ];
    }

    #[DataProvider('everyReason')]
    public function testThePoolPicksTheWordingOfTheReason(ExclusionReason $reason, string $expected): void
    {
        self::assertSame($expected, self::wordings()->text(new SnippetExclusion(self::snippet(['cms']), $reason), 1));
    }

    public function testAReasonWithoutAWordingFailsLoudly(): void
    {
        $pool = new ExclusionWordings([ExclusionReason::SwitchedOff->value => new SwitchedOffWording()]);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('other_stores');

        $pool->text(new SnippetExclusion(self::snippet(['product']), ExclusionReason::OtherStores), 1);
    }

    /**
     * A page snippet for some kinds of page
     *
     * @param list<string> $kinds
     * @param ConditionTreeInterface|null $conditions none when null
     * @return Snippet
     */
    private static function snippet(array $kinds, ?ConditionTreeInterface $conditions = null): Snippet
    {
        return new Snippet(
            4,
            'product-variants',
            'Product with variants',
            '{"@type": "Product"}',
            'Product',
            false,
            true,
            $kinds,
            $conditions ?? PlacementFixture::none(),
            [0],
            null,
            0,
            null,
            ''
        );
    }

    /**
     * The product-type condition the page does not meet
     *
     * @param string|null $pageValue
     * @return ConditionMismatch
     */
    private static function mismatch(?string $pageValue): ConditionMismatch
    {
        return new ConditionMismatch(
            new ConditionLeaf('product_type', null, '==', 'configurable'),
            'product type Configurable',
            'Product type',
            'is',
            ['Configurable'],
            $pageValue
        );
    }
}
