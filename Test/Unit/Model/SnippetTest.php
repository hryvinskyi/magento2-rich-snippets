<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Test\Unit\Model;

use Hryvinskyi\RichSnippets\Api\Data\ConditionAggregator;
use Hryvinskyi\RichSnippets\Api\Data\ConditionTreeInterface;
use Hryvinskyi\RichSnippets\Api\Exception\SnippetRuleException;
use Hryvinskyi\RichSnippets\Api\Exception\SnippetRuleReason;
use Hryvinskyi\RichSnippets\Api\PlacementVocabularyInterface;
use Hryvinskyi\RichSnippets\Model\Snippet;
use Hryvinskyi\RichSnippets\Model\Condition\Tree\ConditionLeaf;
use Hryvinskyi\RichSnippets\Model\Snippet\ExistingStoreViewsInterface;
use Hryvinskyi\RichSnippets\Model\Snippet\Origin;
use Hryvinskyi\RichSnippets\Model\Snippet\TemplateType;
use Hryvinskyi\RichSnippets\Test\Unit\Fake\TreeFactoryFake;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The snippet aggregate keeps its rules for every state it is built with and every change made to it
 */
class SnippetTest extends TestCase
{
    public function testADraftIsSwitchedOffAndOutputNowhereInEveryStoreView(): void
    {
        $snippet = $this->snippet();

        self::assertFalse($snippet->isActive());
        self::assertSame([], $snippet->getPageKinds());
        self::assertTrue($snippet->getConditions()->isEmpty());
        self::assertSame([0], $snippet->getStoreIds());
    }

    #[DataProvider('invalidCodes')]
    public function testACodeOutsideTheGrammarIsRefused(string $code): void
    {
        $this->expectRefusal(SnippetRuleReason::CodeInvalid);

        $this->snippet(code: $code);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function invalidCodes(): array
    {
        return [
            'empty' => [''],
            'one character' => ['a'],
            'leading digit' => ['1product'],
            'leading hyphen' => ['-product'],
            'uppercase' => ['Product'],
            'underscore' => ['return_policy'],
            'space' => ['return policy'],
            'comment closer' => ['x-->'],
            'longer than 64' => ['a' . str_repeat('b', 64)],
        ];
    }

    public function testACodeOfTwoToSixtyFourCharactersIsKept(): void
    {
        self::assertSame('ab', $this->snippet(code: 'ab')->getCode());
        $long = 'a' . str_repeat('-9', 31) . 'z';
        self::assertSame($long, $this->snippet(code: $long)->getCode());
    }

    #[DataProvider('invalidNames')]
    public function testANameThatIsNotASingleLineOfUpTo255CharactersIsRefused(string $name): void
    {
        $this->expectRefusal(SnippetRuleReason::NameInvalid);

        $this->snippet()->rename($name);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function invalidNames(): array
    {
        return [
            'empty' => [''],
            'whitespace' => ['   '],
            'line break' => ["Product\nsnippet"],
            'too long' => [str_repeat('é', 256)],
            'not UTF-8' => ["\xC3\x28"],
        ];
    }

    public function testANameIsKeptWithoutSurroundingWhitespace(): void
    {
        $snippet = $this->snippet();
        $snippet->rename('  x--><script>alert(1)</script>  ');

        self::assertSame('x--><script>alert(1)</script>', $snippet->getName());
    }

    public function testABuildingBlockCannotBeSwitchedOn(): void
    {
        $snippet = $this->snippet(isBlock: true);

        $this->expectRefusal(SnippetRuleReason::BlockCannotBeSwitchedOn);
        $snippet->switchOn();
    }

    public function testABuildingBlockCannotBeBuiltSwitchedOn(): void
    {
        $this->expectRefusal(SnippetRuleReason::BlockCannotBeSwitchedOn);

        $this->snippet(isBlock: true, active: true);
    }

    public function testABuildingBlockCannotBeBuiltWithAPlacement(): void
    {
        $this->expectRefusal(SnippetRuleReason::BlockHasNoPlacement);

        $this->snippet(isBlock: true, pageKinds: ['product']);
    }

    public function testABuildingBlockCannotBePlaced(): void
    {
        $snippet = $this->snippet(isBlock: true);

        $this->expectRefusal(SnippetRuleReason::BlockHasNoPlacement);
        $snippet->place(['product'], $this->none(), [0], $this->vocabulary());
    }

    public function testTurningIntoABlockSwitchesOffAndClearsThePlacement(): void
    {
        $snippet = $this->snippet();
        $snippet->place(
            ['product', 'category'],
            $this->conditions(new ConditionLeaf('product_type', null, '!=', 'configurable')),
            [2, 1],
            $this->vocabulary()
        );
        $snippet->switchOn();

        $snippet->turnIntoBlock($this->none());

        self::assertTrue($snippet->isBlock());
        self::assertFalse($snippet->isActive());
        self::assertSame([], $snippet->getPageKinds());
        self::assertTrue($snippet->getConditions()->isEmpty());
        self::assertSame([0], $snippet->getStoreIds());
    }

    public function testABlockCannotBeGivenConditions(): void
    {
        $snippet = $this->snippet();

        $this->expectRefusal(SnippetRuleReason::BlockHasNoPlacement);
        $snippet->turnIntoBlock($this->conditions(new ConditionLeaf('product_type', null, '==', 'simple')));
    }

    public function testABlockTurnedBackIntoAPageSnippetStaysOffUntilSwitchedOn(): void
    {
        $snippet = $this->snippet(isBlock: true);

        $snippet->turnIntoPageSnippet();

        self::assertFalse($snippet->isBlock());
        self::assertFalse($snippet->isActive());
        $snippet->switchOn();
        self::assertTrue($snippet->isActive());
    }

    public function testAPageSnippetWithNoKindCanBeSwitchedOnAndIsOutputNowhere(): void
    {
        $snippet = $this->snippet();

        $snippet->switchOn();

        self::assertTrue($snippet->isActive());
        self::assertSame([], $snippet->getPageKinds());
    }

    public function testPlacingKeepsEachKindOnceAndEveryStoreViewAsZero(): void
    {
        $snippet = $this->snippet();

        $conditions = $this->conditions(new ConditionLeaf('cms_page', null, '==', 'faq'));
        $snippet->place(['product', 'cms', 'product'], $conditions, [3, 0, 1], $this->vocabulary());

        self::assertSame(['product', 'cms'], $snippet->getPageKinds());
        self::assertSame($conditions, $snippet->getConditions());
        self::assertSame([0], $snippet->getStoreIds());
    }

    public function testPlacingSortsTheStoreViewsAndKeepsEachOnce(): void
    {
        $snippet = $this->snippet();

        $snippet->place(['product'], $this->none(), [3, 1, 3], $this->vocabulary());

        self::assertSame([1, 3], $snippet->getStoreIds());
    }

    public function testAnUnknownKindOfPageIsRefused(): void
    {
        $this->expectRefusal(SnippetRuleReason::PageKindUnknown, ['kind' => 'blog']);

        $this->snippet()->place(['blog'], $this->none(), [0], $this->vocabulary());
    }

    public function testAnUnknownConditionFieldIsRefused(): void
    {
        $this->expectRefusal(SnippetRuleReason::ConditionFieldUnknown, ['field' => 'colour']);

        $this->snippet()->place(
            ['product'],
            $this->conditions(new ConditionLeaf('colour', null, '==', 'red')),
            [0],
            $this->vocabulary()
        );
    }

    public function testAConditionFieldOfAKindTheSnippetIsNotOutputOnIsRefused(): void
    {
        $this->expectRefusal(
            SnippetRuleReason::ConditionFieldOutsideKinds,
            ['field' => 'product_type', 'kind' => 'product']
        );

        $this->snippet()->place(
            ['category'],
            $this->conditions(new ConditionLeaf('product_type', null, '==', 'simple')),
            [0],
            $this->vocabulary()
        );
    }

    public function testNoStoreViewIsRefused(): void
    {
        $this->expectRefusal(SnippetRuleReason::StoresMissing);

        $this->snippet()->place(['product'], $this->none(), [], $this->vocabulary());
    }

    public function testLeavingAStoreViewTakesItOutOfTheListAndKeepsTheSwitch(): void
    {
        $snippet = $this->snippet();
        $snippet->place(['product'], $this->none(), [1, 2], $this->vocabulary());
        $snippet->switchOn();

        $snippet->leaveStoreView(2);

        self::assertSame([1], $snippet->getStoreIds());
        self::assertTrue($snippet->isActive());
    }

    public function testLeavingTheLastStoreViewSwitchesOffAndLeavesItSetForNoStoreView(): void
    {
        $snippet = $this->snippet();
        $snippet->place(['product'], $this->none(), [2], $this->vocabulary());
        $snippet->switchOn();

        $snippet->leaveStoreView(2);

        self::assertSame([], $snippet->getStoreIds());
        self::assertFalse($snippet->isActive());
        self::assertSame(['product'], $snippet->getPageKinds());
    }

    public function testLeavingAStoreViewOfASnippetSetForEveryStoreViewChangesNothing(): void
    {
        $snippet = $this->snippet();
        $snippet->place(['product'], $this->none(), [0], $this->vocabulary());
        $snippet->switchOn();

        $snippet->leaveStoreView(2);

        self::assertSame([0], $snippet->getStoreIds());
        self::assertTrue($snippet->isActive());
    }

    public function testAStoredSnippetSetForNoStoreViewIsRestoredSwitchedOff(): void
    {
        $snippet = $this->snippet(pageKinds: ['product'], storeIds: [], id: 7);

        self::assertSame([], $snippet->getStoreIds());
        self::assertFalse($snippet->isActive());
    }

    public function testASnippetSetForNoStoreViewCannotBeBuiltSwitchedOn(): void
    {
        $this->expectRefusal(SnippetRuleReason::StoresMissing);

        $this->snippet(active: true, pageKinds: ['product'], storeIds: [], id: 7);
    }

    public function testANewSnippetSetForNoStoreViewIsRefused(): void
    {
        $this->expectRefusal(SnippetRuleReason::StoresMissing);

        $this->snippet(pageKinds: ['product'], storeIds: []);
    }

    public function testASnippetSetForNoStoreViewCannotBeSwitchedOnUntilItIsPlaced(): void
    {
        $snippet = $this->snippet(pageKinds: ['product'], storeIds: [], id: 7);

        try {
            $snippet->switchOn();
            self::fail('A snippet set for no store view was switched on.');
        } catch (SnippetRuleException $e) {
            self::assertSame(SnippetRuleReason::StoresMissing, $e->getReason());
        }
        $snippet->place(['product'], $this->none(), [1], $this->vocabulary());
        $snippet->switchOn();

        self::assertTrue($snippet->isActive());
    }

    public function testAStoredSnippetSetForNoStoreViewMayReplaceOneSetForStoreViewsThatAreGone(): void
    {
        $stored = $this->snippet(pageKinds: ['product'], storeIds: [2, 3], id: 7);
        $replacement = $this->snippet(pageKinds: ['product'], storeIds: [], id: 7);

        $replacement->assertKeepsStoreViewsOf($stored, $this->shopWith([1]));

        self::assertSame([], $replacement->getStoreIds());
    }

    public function testAStoredSnippetSetForNoStoreViewCannotReplaceOneSetForAStoreViewTheShopHas(): void
    {
        $stored = $this->snippet(pageKinds: ['product'], storeIds: [1, 3], id: 7);
        $replacement = $this->snippet(pageKinds: ['product'], storeIds: [], id: 7);

        $this->expectRefusal(SnippetRuleReason::StoresMissing);

        $replacement->assertKeepsStoreViewsOf($stored, $this->shopWith([1]));
    }

    public function testASnippetSetForEveryStoreViewIsNeverReplacedByOneSetForNone(): void
    {
        $stored = $this->snippet(pageKinds: ['product'], storeIds: [0], id: 7);
        $replacement = $this->snippet(pageKinds: ['product'], storeIds: [], id: 7);

        $this->expectRefusal(SnippetRuleReason::StoresMissing);

        $replacement->assertKeepsStoreViewsOf($stored, $this->shopWith([0]));
    }

    public function testASnippetAlreadySetForNoStoreViewMayBeReplacedByOneSetForNone(): void
    {
        $stored = $this->snippet(pageKinds: ['product'], storeIds: [], id: 7);
        $replacement = $this->snippet(pageKinds: ['product'], storeIds: [], id: 7);

        $replacement->assertKeepsStoreViewsOf($stored, $this->shopWith([0, 1]));

        self::assertSame([], $replacement->getStoreIds());
    }

    public function testAReplacementSetForAStoreViewIsNotMeasuredAgainstTheShop(): void
    {
        $stored = $this->snippet(pageKinds: ['product'], storeIds: [1], id: 7);
        $replacement = $this->snippet(pageKinds: ['product'], storeIds: [2], id: 7);

        $replacement->assertKeepsStoreViewsOf($stored, $this->shopWith([1, 2]));

        self::assertSame([2], $replacement->getStoreIds());
    }

    public function testASnippetSetForNoStoreViewCanBePlacedAgainWithoutOneAndStaysOff(): void
    {
        $snippet = $this->snippet(pageKinds: ['product'], storeIds: [], id: 7);

        $snippet->place(['product', 'category'], $this->none(), [], $this->vocabulary());

        self::assertSame(['product', 'category'], $snippet->getPageKinds());
        self::assertSame([], $snippet->getStoreIds());
        self::assertFalse($snippet->isActive());
    }

    public function testANegativeStoreIdIsRefused(): void
    {
        $this->expectRefusal(SnippetRuleReason::StoreInvalid, ['store' => '-1']);

        $this->snippet()->place(['product'], $this->none(), [-1], $this->vocabulary());
    }

    public function testARefusedPlacementLeavesThePlacementAsItWas(): void
    {
        $snippet = $this->snippet();
        $none = $this->none();
        $snippet->place(['product'], $none, [1], $this->vocabulary());

        try {
            $snippet->place(
                ['product', 'blog'],
                $this->conditions(new ConditionLeaf('product_type', null, '==', 'simple')),
                [2],
                $this->vocabulary()
            );
            self::fail('The unknown kind was accepted.');
        } catch (SnippetRuleException) {
            self::assertSame(['product'], $snippet->getPageKinds());
            self::assertSame([1], $snippet->getStoreIds());
            self::assertSame($none, $snippet->getConditions());
        }
    }

    public function testANegativeSortOrderIsRefused(): void
    {
        $this->expectRefusal(SnippetRuleReason::SortOrderInvalid);

        $this->snippet()->moveTo(-1);
    }

    public function testChangingTheTemplateTakesTheFirstTypeOfItsRoot(): void
    {
        $snippet = $this->snippet();

        $snippet->changeTemplate('{"@type": ["Product", "Car"], "name": "{{ product.name }}"}', new TemplateType());

        self::assertSame('Product', $snippet->getType());
    }

    public function testATemplateWithoutATypeLeavesTheTypeEmpty(): void
    {
        $snippet = $this->snippet();

        $snippet->changeTemplate('{"name": "x"}', new TemplateType());

        self::assertSame('', $snippet->getType());
    }

    public function testRetypingRewritesTheTypeAtTheTemplatesRoot(): void
    {
        $snippet = $this->snippet();
        $snippet->changeTemplate(
            '{"@context": "https://schema.org", "@type": "Product", "name": "x"}',
            new TemplateType()
        );

        $snippet->retype('ProductGroup', new TemplateType());

        self::assertSame('ProductGroup', $snippet->getType());
        self::assertSame(
            ['@context' => 'https://schema.org', '@type' => 'ProductGroup', 'name' => 'x'],
            json_decode($snippet->getTemplate(), true)
        );
    }

    public function testRetypingToATypeOutsideTheGrammarIsRefusedAndChangesNothing(): void
    {
        $snippet = $this->snippet();
        $template = '{"@type": "Product"}';
        $snippet->changeTemplate($template, new TemplateType());

        try {
            $snippet->retype('product page', new TemplateType());
            self::fail('The type was accepted.');
        } catch (SnippetRuleException $e) {
            self::assertSame(SnippetRuleReason::TypeInvalid, $e->getReason());
            self::assertSame($template, $snippet->getTemplate());
            self::assertSame('Product', $snippet->getType());
        }
    }

    public function testProjectingTheTypeTakesTheTemplatesTypeOverTheStoredOne(): void
    {
        $snippet = $this->snippet(template: '{"@type": "Organization"}', type: 'Product');

        $snippet->projectType(new TemplateType());

        self::assertSame('Organization', $snippet->getType());
    }

    public function testProjectingRefusesATemplateWithoutAType(): void
    {
        $snippet = $this->snippet(template: '{"name": "x"}');

        $this->expectRefusal(SnippetRuleReason::TypeMissing);
        $snippet->projectType(new TemplateType());
    }

    public function testProjectingRefusesAListWithAnEntryOutsideTheGrammar(): void
    {
        $snippet = $this->snippet(template: '{"@type": ["Product", "{{ product.type }}"]}');

        $this->expectRefusal(SnippetRuleReason::TypeInvalid, ['type' => '{{ product.type }}']);
        $snippet->projectType(new TemplateType());
    }

    public function testTheOriginIsRecordedAndForgotten(): void
    {
        $snippet = $this->snippet();

        $snippet->recordOrigin(new Origin('product', 3));
        self::assertSame('product', $snippet->getOrigin()?->getCode());
        self::assertSame(3, $snippet->getOrigin()->getVersion());

        $snippet->recordOrigin(null);
        self::assertNull($snippet->getOrigin());
    }

    public function testAnOriginOutsideTheRulesIsRefused(): void
    {
        $this->expectRefusal(SnippetRuleReason::OriginInvalid);

        new Origin('product', 0);
    }

    public function testAConditionWithoutAFieldIsRefused(): void
    {
        $this->expectRefusal(SnippetRuleReason::ConditionInvalid);

        new ConditionLeaf('', null, '==', 'simple');
    }

    public function testAConditionWithoutAValueIsKeptAsIncomplete(): void
    {
        $leaf = new ConditionLeaf('product_type', null, '==', '');

        self::assertFalse($leaf->isComplete());
    }

    /**
     * A snippet with the values a test needs and a draft's for the rest
     *
     * @param string $code
     * @param string $template
     * @param string $type
     * @param bool $isBlock
     * @param bool $active
     * @param list<string> $pageKinds
     * @param list<int> $storeIds
     * @param int|null $id null for a new snippet
     * @return Snippet
     */
    private function snippet(
        string $code = 'product',
        string $template = '',
        string $type = '',
        bool $isBlock = false,
        bool $active = false,
        array $pageKinds = [],
        array $storeIds = [0],
        ?int $id = null
    ): Snippet {
        return new Snippet(
            $id,
            $code,
            'Product',
            $template,
            $type,
            $isBlock,
            $active,
            $pageKinds,
            $this->none(),
            $storeIds,
            null,
            0,
            null,
            ''
        );
    }

    /**
     * A shop that has store view 0 and the given store views
     *
     * @param list<int> $storeIds
     * @return ExistingStoreViewsInterface
     */
    private function shopWith(array $storeIds): ExistingStoreViewsInterface
    {
        return new class ($storeIds) implements ExistingStoreViewsInterface {
            /**
             * @param list<int> $has
             */
            public function __construct(private readonly array $has)
            {
            }

            public function among(array $storeIds): array
            {
                return array_values(array_intersect($storeIds, [0, ...$this->has]));
            }
        };
    }

    /**
     * No conditions
     *
     * @return ConditionTreeInterface
     */
    private function none(): ConditionTreeInterface
    {
        return (new TreeFactoryFake())->tree();
    }

    /**
     * A tree of all the given conditions
     *
     * @param ConditionLeaf ...$leaves
     * @return ConditionTreeInterface
     */
    private function conditions(ConditionLeaf ...$leaves): ConditionTreeInterface
    {
        return (new TreeFactoryFake())->tree(ConditionAggregator::All, array_values($leaves));
    }

    /**
     * The kinds of page and condition fields the module ships
     *
     * @return PlacementVocabularyInterface
     */
    private function vocabulary(): PlacementVocabularyInterface
    {
        return new class implements PlacementVocabularyInterface {
            private const KINDS = ['home', 'product', 'category', 'cms', 'other'];
            private const FIELDS = [
                'product_type' => 'product',
                'attribute_set' => 'product',
                'product_category' => 'product',
                'category_page' => 'category',
                'cms_page' => 'cms',
            ];

            public function hasPageKind(string $kind): bool
            {
                return in_array($kind, self::KINDS, true);
            }

            public function pageKindOfField(string $field): ?string
            {
                return self::FIELDS[$field] ?? null;
            }
        };
    }

    /**
     * Expect a refusal for a reason, with its parameters when given
     *
     * @param SnippetRuleReason $reason
     * @param array<string,string>|null $params
     * @return void
     */
    private function expectRefusal(SnippetRuleReason $reason, ?array $params = null): void
    {
        $this->expectException(SnippetRuleException::class);
        $this->expectExceptionMessage(
            'The snippet is refused: ' . $reason->value
                . ($params === null ? '' : ' (' . implode(', ', array_map(
                    static fn (string $name, string $value): string => $name . '=' . $value,
                    array_keys($params),
                    $params
                )) . ')')
        );
    }
}
