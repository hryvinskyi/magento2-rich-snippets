<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Overview;

use Hryvinskyi\RichSnippets\Api\Data\KindOverviewInterface;
use Hryvinskyi\RichSnippets\Api\Data\KindOverviewInterfaceFactory;
use Hryvinskyi\RichSnippets\Api\Data\KindSnippetInterface;
use Hryvinskyi\RichSnippets\Api\Data\KindSnippetInterfaceFactory;
use Hryvinskyi\RichSnippets\Api\Data\PlacementCountInterface;
use Hryvinskyi\RichSnippets\Api\Data\SnippetInterface;
use Hryvinskyi\RichSnippets\Api\ExistingPagesInterface;
use Hryvinskyi\RichSnippets\Api\PageKindPoolInterface;
use Hryvinskyi\RichSnippets\Api\PlacementCounterInterface;
use Hryvinskyi\RichSnippets\Model\Check\ExistingPageIds;

/**
 * The Overview's page map: every kind of page, with its pages and the page snippets set for it
 *
 * In one store view a kind has that store view's pages; over every store view each page counts once. The page to
 * look at is the first of the kind in the store view, or in the reference store view over every store view. A
 * snippet with conditions counts the pages that meet them in the scope's store view; over every store view, in the
 * reference store view when the snippet is set for it, else in the first store view it is set for.
 */
class KindMap
{
    /**
     * @param PageKindPoolInterface $kinds
     * @param ExistingPagesInterface $pages
     * @param ExistingPageIds $pageIds
     * @param PlacementCounterInterface $counter
     * @param KindOverviewInterfaceFactory $kindOverviews
     * @param KindSnippetInterfaceFactory $kindSnippets
     */
    public function __construct(
        private readonly PageKindPoolInterface $kinds,
        private readonly ExistingPagesInterface $pages,
        private readonly ExistingPageIds $pageIds,
        private readonly PlacementCounterInterface $counter,
        private readonly KindOverviewInterfaceFactory $kindOverviews,
        private readonly KindSnippetInterfaceFactory $kindSnippets
    ) {
    }

    /**
     * Every kind of page, in the kinds' order
     *
     * @param list<SnippetInterface> $snippets the page snippets of the scope
     * @param list<int> $storeIds the store views of the scope
     * @param int $referenceStoreId the store view that stands for every store view: the scope's own when it has one
     * @return list<KindOverviewInterface>
     */
    public function kinds(array $snippets, array $storeIds, int $referenceStoreId): array
    {
        $map = [];
        foreach ($this->kinds->getCodes() as $kind) {
            $listable = $this->pages->select($kind, $referenceStoreId) !== null;
            $set = array_values(array_filter(
                $snippets,
                static fn (SnippetInterface $snippet): bool => in_array($kind, $snippet->getPageKinds(), true)
            ));
            $map[] = $this->kindOverviews->create([
                'kind' => $kind,
                'pageCount' => $listable ? $this->pageCount($kind, $storeIds) : null,
                'sampleEntityId' => $listable ? $this->pageIds->first($kind, $referenceStoreId) : null,
                'snippets' => array_map(
                    fn (SnippetInterface $snippet): KindSnippetInterface => $this->kindSnippets->create([
                        'snippet' => $snippet,
                        'placementCount' => $listable
                            ? $this->placementCount($snippet, $kind, $storeIds, $referenceStoreId)
                            : null,
                    ]),
                    $set
                ),
            ]);
        }

        return $map;
    }

    /**
     * The pages of a kind in the scope's store views, each once
     *
     * @param string $kind
     * @param list<int> $storeIds
     * @return int
     */
    private function pageCount(string $kind, array $storeIds): int
    {
        return count($storeIds) === 1
            ? $this->pageIds->count($kind, $storeIds[0])
            : $this->pageIds->distinct($kind, $storeIds);
    }

    /**
     * The pages of a kind that meet a snippet's conditions; null when it has none
     *
     * @param SnippetInterface $snippet
     * @param string $kind
     * @param list<int> $storeIds
     * @param int $referenceStoreId
     * @return PlacementCountInterface|null
     */
    private function placementCount(
        SnippetInterface $snippet,
        string $kind,
        array $storeIds,
        int $referenceStoreId
    ): ?PlacementCountInterface {
        if ($snippet->getConditions()->getCompleteLeaves() === []) {
            return null;
        }
        $own = $snippet->getStoreIds();
        $storeId = $own === [SnippetInterface::ALL_STORES] || in_array($referenceStoreId, $own, true)
            ? $referenceStoreId
            : (array_values(array_intersect($own, $storeIds))[0] ?? $referenceStoreId);

        return $this->counter->count([$kind], $snippet->getConditions(), $storeId)[$kind] ?? null;
    }
}
