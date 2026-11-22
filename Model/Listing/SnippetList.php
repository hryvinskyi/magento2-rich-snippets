<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Listing;

use Hryvinskyi\RichSnippets\Api\ConditionSentenceInterface;
use Hryvinskyi\RichSnippets\Api\Data\FlagTone;
use Hryvinskyi\RichSnippets\Api\Data\SnippetFlagInterface;
use Hryvinskyi\RichSnippets\Api\Data\SnippetInterface;
use Hryvinskyi\RichSnippets\Api\Data\SnippetListingInterface;
use Hryvinskyi\RichSnippets\Api\Data\SnippetRowInterface;
use Hryvinskyi\RichSnippets\Api\PageKindPoolInterface;
use Hryvinskyi\RichSnippets\Api\SnippetFlagProviderInterface;
use Hryvinskyi\RichSnippets\Api\SnippetListInterface;
use Hryvinskyi\RichSnippets\Api\SnippetRepositoryInterface;
use Hryvinskyi\RichSnippets\Model\Snippet\IncludeEdgesInterface;
use Hryvinskyi\RichSnippets\Model\Snippet\IncludeGraph;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Lists the snippets with their flags, the snippets they include and are included by, and the counts of the list
 *
 * The includes are read across every stored snippet, so a row names the snippets that use it even when the store
 * view picked hides them. Conditions are worded with the labels of the store view picked, or of the default store
 * view for the list of every store view.
 */
class SnippetList implements SnippetListInterface
{
    /**
     * @param SnippetRepositoryInterface $snippets
     * @param IncludeEdgesInterface $edges
     * @param IncludeGraph $graph
     * @param SnippetFlagProviderInterface $flags
     * @param ConditionSentenceInterface $summary
     * @param PropertyCount $properties
     * @param PageKindPoolInterface $kinds
     * @param StoreManagerInterface $storeManager
     * @param ListingFactoryInterface $listings
     */
    public function __construct(
        private readonly SnippetRepositoryInterface $snippets,
        private readonly IncludeEdgesInterface $edges,
        private readonly IncludeGraph $graph,
        private readonly SnippetFlagProviderInterface $flags,
        private readonly ConditionSentenceInterface $summary,
        private readonly PropertyCount $properties,
        private readonly PageKindPoolInterface $kinds,
        private readonly StoreManagerInterface $storeManager,
        private readonly ListingFactoryInterface $listings
    ) {
    }

    /**
     * @inheritDoc
     */
    public function list(?int $storeId): SnippetListingInterface
    {
        $all = $this->snippets->list();
        $names = [];
        foreach ($all as $snippet) {
            $names[$snippet->getCode()] = $snippet->getName();
        }
        $listed = $storeId === null ? $all : $this->snippets->list($storeId);
        $edges = $this->edges->edges();
        $flags = $this->flags->flags($listed, $storeId);
        $labelStore = $storeId ?? (int) $this->storeManager->getDefaultStoreView()?->getId();
        $storeNames = $this->storeNames();

        $rows = [];
        foreach ($listed as $snippet) {
            $code = $snippet->getCode();
            $rows[] = $this->listings->row(
                $snippet,
                $this->summary->of($snippet->getConditions(), $labelStore),
                $this->namesOfStores($snippet, $storeNames),
                $this->properties->of($snippet->getTemplate()),
                $this->named($edges[$code] ?? [], $names),
                array_map(
                    static fn (?string $name): string => (string) $name,
                    $this->named($this->graph->directIncludersOf($code, $edges), $names)
                ),
                $flags[$code] ?? []
            );
        }

        return $this->counted($rows);
    }

    /**
     * The list with its counts
     *
     * @param list<SnippetRowInterface> $rows
     * @return SnippetListingInterface
     */
    private function counted(array $rows): SnippetListingInterface
    {
        $kindCounts = array_fill_keys($this->kinds->getCodes(), 0);
        $flagCounts = array_fill_keys(
            array_map(static fn (FlagTone $tone): string => $tone->value, FlagTone::cases()),
            0
        );
        $attention = $on = $off = $blocks = 0;
        foreach ($rows as $row) {
            $snippet = $row->getSnippet();
            $tones = array_unique(array_map(
                static fn (SnippetFlagInterface $flag): string => $flag->getTone()->value,
                $row->getFlags()
            ));
            foreach ($tones as $tone) {
                $flagCounts[$tone]++;
            }
            if (array_intersect($tones, [FlagTone::Bad->value, FlagTone::Warn->value]) !== []) {
                $attention++;
            }
            if ($snippet->isBlock()) {
                $blocks++;
                continue;
            }
            $on += (int) $snippet->isActive();
            $off += (int) !$snippet->isActive();
            foreach ($snippet->getPageKinds() as $kind) {
                $kindCounts[$kind] = ($kindCounts[$kind] ?? 0) + 1;
            }
        }

        return $this->listings->listing($rows, $kindCounts, $flagCounts, $attention, $on, $off, $blocks);
    }

    /**
     * The names of the store views a snippet is set for; none when it is set for every store view
     *
     * @param SnippetInterface $snippet
     * @param array<int,string> $storeNames
     * @return list<string>
     */
    private function namesOfStores(SnippetInterface $snippet, array $storeNames): array
    {
        $names = [];
        foreach ($snippet->getStoreIds() as $id) {
            if ($id !== SnippetInterface::ALL_STORES && isset($storeNames[$id])) {
                $names[] = $storeNames[$id];
            }
        }

        return $names;
    }

    /**
     * The name of each code, null for a code no snippet has
     *
     * @param list<string> $codes
     * @param array<string,string> $names
     * @return array<string, string|null>
     */
    private function named(array $codes, array $names): array
    {
        $named = [];
        foreach ($codes as $code) {
            $named[$code] = $names[$code] ?? null;
        }

        return $named;
    }

    /**
     * The store views' names, by ID
     *
     * @return array<int, string>
     */
    private function storeNames(): array
    {
        $names = [];
        foreach ($this->storeManager->getStores() as $store) {
            $names[(int) $store->getId()] = (string) $store->getName();
        }

        return $names;
    }
}
