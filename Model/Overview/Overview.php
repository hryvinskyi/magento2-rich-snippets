<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Overview;

use Hryvinskyi\RichSnippets\Api\Data\AttentionScopeInterfaceFactory;
use Hryvinskyi\RichSnippets\Api\Data\CheckState;
use Hryvinskyi\RichSnippets\Api\Data\OverviewReportInterface;
use Hryvinskyi\RichSnippets\Api\Data\OverviewReportInterfaceFactory;
use Hryvinskyi\RichSnippets\Api\Data\SetupState;
use Hryvinskyi\RichSnippets\Api\Data\SnippetInterface;
use Hryvinskyi\RichSnippets\Api\OverviewInterface;
use Hryvinskyi\RichSnippets\Api\PageKindPoolInterface;
use Hryvinskyi\RichSnippets\Api\SnippetRepositoryInterface;
use Hryvinskyi\RichSnippets\Model\Check\AggregateMetric;
use Hryvinskyi\RichSnippets\Model\Check\CheckResultReaderInterface;
use Hryvinskyi\RichSnippets\Model\Check\CheckRunStoreInterface;
use Hryvinskyi\RichSnippets\Model\Snippet\SnippetHistoryInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Reads the Overview of a store view, or of every store view
 *
 * Nothing is set up while the shop has no page snippet at all, whichever store view is asked about. The scope is live
 * when one of its switched-on page snippets is set for a kind of page; else it is off. The counts of pages come from
 * the last finished catalog check: its counts of the store view, or its counts over every store view. While the scope
 * is not live no page carries a snippet, none is invalid and no Google result is counted, whatever the last check
 * found before.
 */
class Overview implements OverviewInterface
{
    /**
     * @param SnippetRepositoryInterface $snippets
     * @param SnippetHistoryInterface $history
     * @param StoreManagerInterface $storeManager
     * @param PageKindPoolInterface $kinds
     * @param CheckRunStoreInterface $runs
     * @param CheckResultReaderInterface $results
     * @param FeatureOverviews $features
     * @param OutputFacts $outputFacts
     * @param KindMap $kindMap
     * @param AttentionItems $attention
     * @param AttentionScopeInterfaceFactory $scopes
     * @param OverviewReportInterfaceFactory $reports
     */
    public function __construct(
        private readonly SnippetRepositoryInterface $snippets,
        private readonly SnippetHistoryInterface $history,
        private readonly StoreManagerInterface $storeManager,
        private readonly PageKindPoolInterface $kinds,
        private readonly CheckRunStoreInterface $runs,
        private readonly CheckResultReaderInterface $results,
        private readonly FeatureOverviews $features,
        private readonly OutputFacts $outputFacts,
        private readonly KindMap $kindMap,
        private readonly AttentionItems $attention,
        private readonly AttentionScopeInterfaceFactory $scopes,
        private readonly OverviewReportInterfaceFactory $reports
    ) {
    }

    /**
     * @inheritDoc
     */
    public function read(?int $storeId): OverviewReportInterface
    {
        $storeIds = $this->scopeStoreIds($storeId);
        $inScope = $this->snippets->list($storeId, null, null, false);
        $switchedOn = array_values(array_filter(
            $inScope,
            static fn (SnippetInterface $snippet): bool => $snippet->isActive()
        ));
        $shopSnippets = $storeId === null ? $inScope : $this->snippets->list(null, null, null, false);
        $state = $this->state($shopSnippets, $switchedOn);
        $live = $state === SetupState::Live;
        $run = $this->runs->latest(CheckState::Done);
        $counts = $run === null || !$live ? [] : $this->results->aggregates($run->getId(), $storeId ?? 0);
        $themeMarkup = $this->outputFacts->themeMarkup($storeIds);

        return $this->reports->create([
            'storeId' => $storeId,
            'setupState' => $state,
            'snippetsAdded' => count($inScope),
            'snippetsOn' => count($switchedOn),
            'kindsOn' => $this->kindsOf($switchedOn),
            'storeIdsOn' => $this->storeIdsOf($switchedOn, $storeIds),
            'firstAddedAt' => $this->history->firstAddedAt(array_values(array_filter(array_map(
                static fn (SnippetInterface $snippet): ?int => $snippet->getId(),
                $inScope
            ), static fn (?int $id): bool => $id !== null))),
            'lastRun' => $run,
            'pagesWithSnippets' => $run === null
                ? null
                : ($counts[AggregateMetric::PagesWithSnippets->value][''] ?? 0),
            'invalidPagesByKind' => $counts[AggregateMetric::InvalidPages->value] ?? [],
            'outputFormat' => $this->outputFacts->format($storeIds),
            'outputPosition' => $this->outputFacts->position($storeIds),
            'themeMarkup' => $themeMarkup,
            'kinds' => $this->kindMap->kinds($inScope, $storeIds, $storeId ?? $this->referenceStoreId($storeIds)),
            'features' => $live ? $this->features->from($counts, $switchedOn) : [],
            'attentionItems' => $this->attention->items($this->scopes->create([
                'storeId' => $storeId,
                'setupState' => $state,
                'themeMarkup' => $themeMarkup,
                'snippets' => $inScope,
            ])),
        ]);
    }

    /**
     * The store views of the scope: the one asked about, or every store view of the shop, in ascending order
     *
     * @param int|null $storeId
     * @return list<int>
     * @throws NoSuchEntityException when the shop has no store view with the ID
     */
    private function scopeStoreIds(?int $storeId): array
    {
        $storeIds = [];
        foreach ($this->storeManager->getStores() as $store) {
            $storeIds[] = (int) $store->getId();
        }
        sort($storeIds);
        if ($storeId === null) {
            return $storeIds;
        }
        if (!in_array($storeId, $storeIds, true)) {
            throw new NoSuchEntityException(__('The shop has no store view with the ID %1.', $storeId));
        }

        return [$storeId];
    }

    /**
     * How far the shop has set up its snippets in the scope
     *
     * @param list<SnippetInterface> $shopSnippets every page snippet of the shop
     * @param list<SnippetInterface> $switchedOn the switched-on page snippets of the scope
     * @return SetupState
     */
    private function state(array $shopSnippets, array $switchedOn): SetupState
    {
        if ($shopSnippets === []) {
            return SetupState::None;
        }
        foreach ($switchedOn as $snippet) {
            if ($snippet->getPageKinds() !== []) {
                return SetupState::Live;
            }
        }

        return SetupState::Off;
    }

    /**
     * The kinds of page the snippets are set for, in the kinds' order
     *
     * @param list<SnippetInterface> $snippets
     * @return list<string>
     */
    private function kindsOf(array $snippets): array
    {
        $set = [];
        foreach ($snippets as $snippet) {
            $set += array_fill_keys($snippet->getPageKinds(), true);
        }

        return array_values(array_filter(
            $this->kinds->getCodes(),
            static fn (string $kind): bool => isset($set[$kind])
        ));
    }

    /**
     * The store views of the scope the snippets are output in, in ascending order
     *
     * @param list<SnippetInterface> $snippets
     * @param list<int> $storeIds the store views of the scope
     * @return list<int>
     */
    private function storeIdsOf(array $snippets, array $storeIds): array
    {
        $set = [];
        foreach ($snippets as $snippet) {
            $own = $snippet->getStoreIds();
            $set += array_fill_keys(
                $own === [SnippetInterface::ALL_STORES] ? $storeIds : array_intersect($own, $storeIds),
                true
            );
        }
        $ids = array_map('intval', array_keys($set));
        sort($ids);

        return $ids;
    }

    /**
     * The store view that stands for every store view where one is needed: the default one
     *
     * @param list<int> $storeIds
     * @return int
     */
    private function referenceStoreId(array $storeIds): int
    {
        $default = $this->storeManager->getDefaultStoreView();

        return $default === null ? ($storeIds[0] ?? 0) : (int) $default->getId();
    }
}
