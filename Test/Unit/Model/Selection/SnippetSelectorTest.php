<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Test\Unit\Model\Selection;

use Hryvinskyi\RichSnippets\Api\Data\ConditionMismatchInterface;
use Hryvinskyi\RichSnippets\Api\Data\ExclusionReason;
use Hryvinskyi\RichSnippets\Api\Data\SnippetExclusionInterface;
use Hryvinskyi\RichSnippets\Api\Data\SnippetInterface;
use Hryvinskyi\RichSnippets\Api\SnippetRepositoryInterface;
use Hryvinskyi\RichSnippets\Model\Page\PageEntity;
use Hryvinskyi\RichSnippets\Model\Selection\CandidatePrefetch;
use Hryvinskyi\RichSnippets\Model\Selection\SelectionIndexInterface;
use Hryvinskyi\RichSnippets\Model\Selection\SnippetExclusion;
use Hryvinskyi\RichSnippets\Model\Selection\SnippetExclusionFactoryInterface;
use Hryvinskyi\RichSnippets\Model\Selection\SnippetSelector;
use Hryvinskyi\RichSnippets\Model\Snippet;
use Hryvinskyi\RichSnippets\Test\Unit\Fake\PlacementFixture;
use PHPUnit\Framework\TestCase;

/**
 * A page outputs the snippets of its kind and store view whose conditions it meets; every other page snippet is
 * explained with the first reason that applies
 */
class SnippetSelectorTest extends TestCase
{
    /**
     * @var CandidatePrefetch|null the prefetch a test watches; a silent one when null
     */
    private ?CandidatePrefetch $prefetch = null;

    public function testTheCandidatesOfThePageAreNamedToTheDataSourcesBeforeTheirConditionsAreRead(): void
    {
        $product = $this->snippet(1, 'product', true, ['product']);
        $variants = $this->snippet(2, 'variants', true, ['product'], [['product_type', '==', 'configurable']]);
        $page = new PageEntity('product', 1, 1);
        $prefetch = $this->createMock(CandidatePrefetch::class);
        $prefetch->expects(self::once())->method('forCandidates')->with($page, [$product, $variants]);
        $this->prefetch = $prefetch;

        $selector = $this->selector(['product' => [$product, $variants]], []);

        self::assertSame(['product'], $this->codes($selector->select($page)));
    }

    public function testThePageOutputsTheIndexedSnippetsWhoseConditionsItMeets(): void
    {
        $product = $this->snippet(1, 'product', true, ['product']);
        $variants = $this->snippet(2, 'variants', true, ['product'], [['product_type', '==', 'configurable']]);
        $simpleOnly = $this->snippet(3, 'simple-only', true, ['product'], [['product_type', '!=', 'configurable']]);
        $selector = $this->selector(['product' => [$product, $variants, $simpleOnly]], []);

        self::assertSame(['product', 'simple-only'], $this->codes($selector->select(new PageEntity('product', 1, 1))));
        self::assertSame(['product', 'variants'], $this->codes($selector->select(new PageEntity('product', 2, 1))));
        self::assertSame([], $selector->select(new PageEntity('home', null, 1)));
    }

    public function testEachSnippetNotOutputIsGivenTheFirstReasonThatApplies(): void
    {
        $snippets = [
            $this->snippet(1, 'switched-off', false, ['product'], [['product_type', '==', 'bundle']], [2]),
            $this->snippet(2, 'other-store', true, ['home'], [], [2, 3]),
            $this->snippet(3, 'nowhere', true, []),
            $this->snippet(4, 'organization', true, ['home']),
            $this->snippet(5, 'variants', true, ['product'], [['product_type', '==', 'configurable']]),
            $this->snippet(6, 'product', true, ['product', 'category'], [], [1]),
            $this->snippet(7, 'faq', true, ['cms'], [['cms_page', '==', 'faq']]),
        ];
        $selector = $this->selector([], $snippets);

        self::assertSame(
            [
                ['switched-off', ExclusionReason::SwitchedOff, []],
                ['other-store', ExclusionReason::OtherStores, []],
                ['nowhere', ExclusionReason::NoPageKinds, []],
                ['organization', ExclusionReason::OtherKinds, []],
                ['variants', ExclusionReason::ConditionsNotMet, [['Product type', ['Configurable'], 'Simple']]],
                ['faq', ExclusionReason::OtherKinds, []],
            ],
            $this->explained($selector->explain(new PageEntity('product', 1, 1)))
        );
    }

    public function testEverySnippetIsExplainedOnAPageOfAKindNoneIsSetFor(): void
    {
        $selector = $this->selector([], [
            $this->snippet(1, 'organization', true, ['home']),
            $this->snippet(2, 'product', true, ['product', 'category', 'cms']),
        ]);

        self::assertSame(
            [['organization', ExclusionReason::OtherKinds, []], ['product', ExclusionReason::OtherKinds, []]],
            $this->explained($selector->explain(new PageEntity('other', null, 1)))
        );
    }

    /**
     * A selector over an index and stored snippets
     *
     * @param array<string, list<SnippetInterface>> $indexed the switched-on snippets of store view 1, by kind
     * @param list<SnippetInterface> $stored every stored page snippet
     * @return SnippetSelector
     */
    private function selector(array $indexed, array $stored): SnippetSelector
    {
        $index = $this->createStub(SelectionIndexInterface::class);
        $index->method('snippetsFor')->willReturnCallback(
            static fn (string $kind, int $storeId): array => $storeId === 1 ? ($indexed[$kind] ?? []) : []
        );
        $repository = $this->createStub(SnippetRepositoryInterface::class);
        $repository->method('list')->willReturnCallback(
            static fn (?int $storeId, ?string $kind, ?bool $active, ?bool $block): array => $block === false
                ? $stored
                : []
        );
        $exclusions = new class implements SnippetExclusionFactoryInterface {
            public function create(
                SnippetInterface $snippet,
                ExclusionReason $reason,
                array $mismatches = []
            ): SnippetExclusionInterface {
                return new SnippetExclusion($snippet, $reason, $mismatches);
            }
        };

        return new SnippetSelector(
            $index,
            PlacementFixture::conditions(),
            $repository,
            $exclusions,
            $this->prefetch ?? $this->createStub(CandidatePrefetch::class)
        );
    }

    /**
     * A page snippet
     *
     * @param int $id
     * @param string $code
     * @param bool $active
     * @param list<string> $kinds
     * @param list<array{string, string, string}> $conditions
     * @param list<int> $storeIds
     * @return Snippet
     */
    private function snippet(
        int $id,
        string $code,
        bool $active,
        array $kinds,
        array $conditions = [],
        array $storeIds = [0]
    ): Snippet {
        return new Snippet(
            $id,
            $code,
            ucfirst($code),
            '{"@type": "Thing"}',
            'Thing',
            false,
            $active,
            $kinds,
            PlacementFixture::leaves($conditions),
            $storeIds,
            null,
            $id,
            null,
            ''
        );
    }

    /**
     * @param list<SnippetInterface> $snippets
     * @return list<string>
     */
    private function codes(array $snippets): array
    {
        return array_map(static fn (SnippetInterface $snippet): string => $snippet->getCode(), $snippets);
    }

    /**
     * Exclusions as code, reason and each mismatch's field label, value labels and page value
     *
     * @param list<SnippetExclusionInterface> $exclusions
     * @return list<array{string, ExclusionReason, list<array{string, list<string>, string|null}>}>
     */
    private function explained(array $exclusions): array
    {
        return array_map(
            static fn (SnippetExclusionInterface $exclusion): array => [
                $exclusion->getSnippet()->getCode(),
                $exclusion->getReason(),
                array_map(
                    static fn (ConditionMismatchInterface $mismatch): array => [
                        $mismatch->getFieldLabel(),
                        $mismatch->getValueLabels(),
                        $mismatch->getPageValue(),
                    ],
                    $exclusion->getMismatches()
                ),
            ],
            $exclusions
        );
    }
}
