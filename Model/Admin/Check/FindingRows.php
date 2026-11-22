<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Admin\Check;

use Hryvinskyi\AdminUi\Api\Page\PluralPhrasesInterface;
use Hryvinskyi\RichSnippets\Api\Data\FindingInterface;
use Hryvinskyi\RichSnippets\Api\Data\FindingItemsInterface;
use Hryvinskyi\RichSnippets\Api\Data\PropertyTone;
use Hryvinskyi\RichSnippets\Model\Check\FindingWording;
use Hryvinskyi\RichSnippets\Model\Wording\WordList;

/**
 * Writes the findings of the last check as the "Whole catalog" mode's table and drawer read them
 *
 * The table is `{"store", "subtitle", "findings": [row]}`, a row `{"key", "tone", "title", "description", "fix",
 * "snippets": [{"code", "name"}], "pages", "allProducts", "count", "meta", "label", "fileName", "saved"}`: `count`
 * is the pages with their noun ("23 products"), `meta` the drawer's line under its title, `label` the row's
 * accessible name, `saved` the message after the download. A snippet deleted since the run is named by its code.
 * The drawer's list is `{"finding": row, "total", "page", "pageSize", "note", "items": [{"ref", "kind", "id",
 * "store", "label", "sku", "group"}]}`, where `ref` names the page as the page picker does (`product:12`, `home`)
 * and `note` says which part of the list is shown when it is not all of it.
 *
 * @phpstan-type Row array{key: string, tone: string, title: string, description: string, fix: string,
 *     snippets: list<array{code: string, name: string}>, pages: int, allProducts: bool, count: string,
 *     meta: string, label: string, fileName: string, saved: string}
 * @phpstan-type Item array{ref: string, kind: string, id: int|null, store: int, label: string, sku: string|null,
 *     group: string|null}
 */
class FindingRows
{
    private const FILE_NAME = 'rich-snippets-%s.csv';
    private const HOME = 'home';

    /**
     * @param FindingWording $wording
     * @param WordList $words
     * @param PluralPhrasesInterface $plurals
     */
    public function __construct(
        private readonly FindingWording $wording,
        private readonly WordList $words,
        private readonly PluralPhrasesInterface $plurals
    ) {
    }

    /**
     * The table of findings
     *
     * @param list<FindingInterface> $findings worst first
     * @param int|null $storeId
     * @param array<string,string> $names the snippets' names by code
     * @return array{store: int|null, subtitle: string, findings: list<Row>}
     */
    public function table(array $findings, ?int $storeId, array $names): array
    {
        return [
            'store' => $storeId,
            'subtitle' => $this->subtitle($findings),
            'findings' => array_map(fn (FindingInterface $finding): array => $this->row($finding, $names), $findings),
        ];
    }

    /**
     * Part of the list of pages behind a finding
     *
     * @param FindingItemsInterface $items
     * @param int $page
     * @param int $pageSize
     * @param array<string,string> $names the snippets' names by code
     * @return array{finding: Row, total: int, page: int, pageSize: int, note: string|null, items: list<Item>}
     */
    public function items(FindingItemsInterface $items, int $page, int $pageSize, array $names): array
    {
        $listed = [];
        foreach ($items->getItems() as $item) {
            $id = $item->getEntityId();
            $listed[] = [
                'ref' => $item->getKind() === self::HOME || $id === null
                    ? $item->getKind()
                    : $item->getKind() . ':' . $id,
                'kind' => $item->getKind(),
                'id' => $id,
                'store' => $item->getStoreId(),
                'label' => $item->getLabel(),
                'sku' => $item->getSku(),
                'group' => $item->getGroup(),
            ];
        }

        return [
            'finding' => $this->row($items->getFinding(), $names),
            'total' => $items->getTotal(),
            'page' => $page,
            'pageSize' => $pageSize,
            'note' => $this->note($items->getTotal(), count($listed), $page, $pageSize),
            'items' => $listed,
        ];
    }

    /**
     * One finding's row
     *
     * @param FindingInterface $finding
     * @param array<string,string> $names
     * @return Row
     */
    private function row(FindingInterface $finding, array $names): array
    {
        $title = $this->wording->title($finding);
        $count = $this->wording->count($finding, $finding->getPages());
        $snippets = array_map(
            static fn (string $code): array => ['code' => $code, 'name' => $names[$code] ?? $code],
            $finding->getSnippetCodes()
        );
        $fileName = sprintf(self::FILE_NAME, $finding->getKey());

        return [
            'key' => $finding->getKey(),
            'tone' => $finding->getTone()->value,
            'title' => $title,
            'description' => $this->wording->description($finding),
            'fix' => $this->wording->fix($finding),
            'snippets' => $snippets,
            'pages' => $finding->getPages(),
            'allProducts' => $this->wording->allProducts($finding),
            'count' => $count,
            'meta' => (string) __('%1 · %2', $count, $this->words->join(array_column($snippets, 'name'))),
            'label' => (string) __('%1: %2. Show them.', $title, $count),
            'fileName' => $fileName,
            'saved' => $this->wording->saved($finding, $finding->getPages(), $fileName),
        ];
    }

    /**
     * The line under the table's title
     *
     * @param list<FindingInterface> $findings
     * @return string
     */
    private function subtitle(array $findings): string
    {
        $toFix = count(array_filter(
            $findings,
            static fn (FindingInterface $finding): bool => $finding->getTone() !== PropertyTone::Note
        ));
        $nothing = count($findings) - $toFix;
        if ($toFix === 0) {
            return (string) __('Nothing to fix.');
        }
        $fix = $this->plurals->sentence(
            $toFix,
            __('%1 kind of gap to fix', $toFix),
            __('%1 kinds of gap to fix', $toFix)
        );
        if ($nothing === 0) {
            return (string) __('%1. Click one to see the pages.', $fix);
        }
        $needsNothing = $this->plurals->sentence(
            $nothing,
            __('%1 that needs nothing', $nothing),
            __('%1 that need nothing', $nothing)
        );

        return (string) __('%1, %2. Click one to see the pages.', $fix, $needsNothing);
    }

    /**
     * Which part of the list is shown, when it is not all of it
     *
     * @param int $total
     * @param int $listed
     * @param int $page
     * @param int $pageSize
     * @return string|null
     */
    private function note(int $total, int $listed, int $page, int $pageSize): ?string
    {
        if ($listed === 0 || $listed >= $total) {
            return null;
        }
        if ($page === 1) {
            return (string) __('The first %1 of %2. The download has all of them.', $listed, $total);
        }
        $from = ($page - 1) * $pageSize + 1;

        return (string) __('%1 to %2 of %3. The download has all of them.', $from, $from + $listed - 1, $total);
    }
}
