<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Selection;

use Hryvinskyi\RichSnippets\Api\Data\ConditionMismatchInterface;
use Hryvinskyi\RichSnippets\Api\Data\ExclusionReason;
use Hryvinskyi\RichSnippets\Api\Data\PageEntityInterface;
use Hryvinskyi\RichSnippets\Api\Data\SnippetExclusionInterface;
use Hryvinskyi\RichSnippets\Api\Data\SnippetInterface;
use Hryvinskyi\RichSnippets\Api\SnippetRepositoryInterface;
use Hryvinskyi\RichSnippets\Api\SnippetSelectorInterface;
use Hryvinskyi\RichSnippets\Model\Placement\PlacementConditions;

/**
 * Selects a page's snippets from the store view's selection index, and explains the others from the stored snippets
 *
 * The storefront path reads the index, so a page costs no snippet query once the index is kept, and names what the
 * candidates read to the data sources first, so their conditions cost no query of their own; the explanation, an
 * admin's question, reads every stored page snippet. Both test the conditions the same way.
 */
class SnippetSelector implements SnippetSelectorInterface
{
    /**
     * @param SelectionIndexInterface $index
     * @param PlacementConditions $conditions
     * @param SnippetRepositoryInterface $snippets
     * @param SnippetExclusionFactoryInterface $exclusions
     * @param CandidatePrefetch $prefetch
     */
    public function __construct(
        private readonly SelectionIndexInterface $index,
        private readonly PlacementConditions $conditions,
        private readonly SnippetRepositoryInterface $snippets,
        private readonly SnippetExclusionFactoryInterface $exclusions,
        private readonly CandidatePrefetch $prefetch
    ) {
    }

    /**
     * @inheritDoc
     */
    public function select(PageEntityInterface $page): array
    {
        $candidates = $this->index->snippetsFor($page->getKind(), $page->getStoreId());
        $this->prefetch->forCandidates($page, $candidates);

        return array_values(array_filter(
            $candidates,
            fn (SnippetInterface $snippet): bool => $this->conditions->holds($snippet->getConditions(), $page)
        ));
    }

    /**
     * @inheritDoc
     */
    public function explain(PageEntityInterface $page): array
    {
        $exclusions = [];
        foreach ($this->snippets->list(null, null, null, false) as $snippet) {
            $exclusion = $this->exclusion($snippet, $page);
            if ($exclusion !== null) {
                $exclusions[] = $exclusion;
            }
        }

        return $exclusions;
    }

    /**
     * Why a page snippet is not output on the page; null when it is
     *
     * @param SnippetInterface $snippet
     * @param PageEntityInterface $page
     * @return SnippetExclusionInterface|null
     */
    private function exclusion(SnippetInterface $snippet, PageEntityInterface $page): ?SnippetExclusionInterface
    {
        if (!$snippet->isActive()) {
            return $this->excluded($snippet, ExclusionReason::SwitchedOff);
        }
        $stores = $snippet->getStoreIds();
        if ($stores !== [SnippetInterface::ALL_STORES] && !in_array($page->getStoreId(), $stores, true)) {
            return $this->excluded($snippet, ExclusionReason::OtherStores);
        }
        if ($snippet->getPageKinds() === []) {
            return $this->excluded($snippet, ExclusionReason::NoPageKinds);
        }
        if (!in_array($page->getKind(), $snippet->getPageKinds(), true)) {
            return $this->excluded($snippet, ExclusionReason::OtherKinds);
        }
        $mismatches = $this->conditions->mismatches($snippet->getConditions(), $page);

        return $mismatches === [] ? null : $this->excluded($snippet, ExclusionReason::ConditionsNotMet, $mismatches);
    }

    /**
     * An exclusion of a snippet for a reason
     *
     * @param SnippetInterface $snippet
     * @param ExclusionReason $reason
     * @param list<ConditionMismatchInterface> $mismatches
     * @return SnippetExclusionInterface
     */
    private function excluded(
        SnippetInterface $snippet,
        ExclusionReason $reason,
        array $mismatches = []
    ): SnippetExclusionInterface {
        return $this->exclusions->create($snippet, $reason, $mismatches);
    }
}
