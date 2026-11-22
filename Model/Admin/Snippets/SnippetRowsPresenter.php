<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Admin\Snippets;

use Hryvinskyi\RichSnippets\Api\ConditionTreePrinterInterface;
use Hryvinskyi\RichSnippets\Api\Data\FlagTone;
use Hryvinskyi\RichSnippets\Api\Data\SnippetListingInterface;
use Hryvinskyi\RichSnippets\Api\Data\SnippetRowInterface;

/**
 * Presents the snippet list as the Snippets page reads it: `{"rows": [row], "counts": {"all", "attention", "kinds":
 * {kind: n}, "blocks", "on", "off", "flags": {"bad", "warn", "note"}}}`
 *
 * A row is `{"id", "code", "name", "type", "isBlock", "active", "pageKinds", "conditions", "conditionSummary",
 * "storeIds", "storeNames", "propertyCount", "uses": [{"code", "name"}], "usedIn": [{"code", "name"}], "updatedAt",
 * "updatedBy", "flags": {"bad", "warn", "note"}}`, `conditions` being the expression builder's text (empty for none);
 * a flag's text is the first one of its tone, null when there is none, and a `uses` name is null for a code no
 * snippet has.
 *
 * @phpstan-type Reference array{code: string, name: string|null}
 * @phpstan-type Row array{id: int, code: string, name: string, type: string, isBlock: bool, active: bool,
 *     pageKinds: list<string>, conditions: string, conditionSummary: string, storeIds: list<int>,
 *     storeNames: list<string>, propertyCount: int,
 *     uses: list<Reference>, usedIn: list<Reference>, updatedAt: string|null, updatedBy: string,
 *     flags: array<string, string|null>}
 * @phpstan-type Counts array{all: int, attention: int, kinds: array<string, int>, blocks: int, on: int, off: int,
 *     flags: array<string, int>}
 * @phpstan-type Listing array{rows: list<Row>, counts: Counts}
 */
class SnippetRowsPresenter
{
    /**
     * @param ConditionTreePrinterInterface $printer
     */
    public function __construct(private readonly ConditionTreePrinterInterface $printer)
    {
    }

    /**
     * The list as the page reads it
     *
     * @param SnippetListingInterface $listing
     * @return Listing
     */
    public function present(SnippetListingInterface $listing): array
    {
        $rows = array_map(fn (SnippetRowInterface $row): array => $this->row($row), $listing->getRows());

        return [
            'rows' => $rows,
            'counts' => [
                'all' => count($rows),
                'attention' => $listing->getAttentionCount(),
                'kinds' => $listing->getKindCounts(),
                'blocks' => $listing->getBlockCount(),
                'on' => $listing->getOnCount(),
                'off' => $listing->getOffCount(),
                'flags' => $listing->getFlagCounts(),
            ],
        ];
    }

    /**
     * One row
     *
     * @param SnippetRowInterface $row
     * @return Row
     */
    private function row(SnippetRowInterface $row): array
    {
        $snippet = $row->getSnippet();
        $flags = array_fill_keys(
            array_map(static fn (FlagTone $tone): string => $tone->value, FlagTone::cases()),
            null
        );
        foreach ($row->getFlags() as $flag) {
            $flags[$flag->getTone()->value] ??= $flag->getText();
        }

        return [
            'id' => (int) $snippet->getId(),
            'code' => $snippet->getCode(),
            'name' => $snippet->getName(),
            'type' => $snippet->getType(),
            'isBlock' => $snippet->isBlock(),
            'active' => $snippet->isActive(),
            'pageKinds' => $snippet->getPageKinds(),
            'conditions' => $this->printer->print($snippet->getConditions()),
            'conditionSummary' => $row->getConditionSummary(),
            'storeIds' => $snippet->getStoreIds(),
            'storeNames' => $row->getStoreNames(),
            'propertyCount' => $row->getPropertyCount(),
            'uses' => $this->references($row->getUses()),
            'usedIn' => $this->references($row->getUsedIn()),
            'updatedAt' => $snippet->getUpdatedAt(),
            'updatedBy' => $snippet->getUpdatedBy(),
            'flags' => $flags,
        ];
    }

    /**
     * Names by code as a list of references
     *
     * @param array<string,string|null> $names
     * @return list<Reference>
     */
    private function references(array $names): array
    {
        $references = [];
        foreach ($names as $code => $name) {
            $references[] = ['code' => (string) $code, 'name' => $name];
        }

        return $references;
    }
}
