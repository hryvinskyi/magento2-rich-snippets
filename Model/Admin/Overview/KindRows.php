<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Admin\Overview;

use Hryvinskyi\AdminUi\Api\Page\PagePermissionsInterface;
use Hryvinskyi\RichSnippets\Api\Data\KindOverviewInterface;
use Hryvinskyi\RichSnippets\Api\Data\KindSnippetInterface;
use Hryvinskyi\RichSnippets\Api\Data\OverviewReportInterface;
use Hryvinskyi\RichSnippets\Api\Data\SetupState;
use Hryvinskyi\RichSnippets\Api\Data\SnippetInterface;
use Hryvinskyi\RichSnippets\Model\Placement\KindLabels;
use Hryvinskyi\RichSnippets\Model\Placement\LimitingValue;
use Hryvinskyi\RichSnippets\Model\Wording\CountWords;
use Hryvinskyi\RichSnippets\Model\Wording\WordList;

/**
 * Writes the Overview's page map, "What each kind of page outputs", one row per kind of page
 *
 * A row is `{"kind", "label", "icon", "pages", "see", "empty", "snippets"}`: `pages` counts the kind's pages, or
 * describes them when they cannot be listed; `see` opens one of its pages on the Test page, `{"route", "params"}`,
 * while the snippets are live, the kind has a page to show and the admin may run tests, else null; `empty` is what
 * the row says when no snippet is set for the kind, else null. A snippet chip is `{"id", "code", "name", "on",
 * "sub"}`: `sub` is "off" for a switched-off snippet, else what its conditions limit the kind's pages to and, over
 * every store view, the store views it is limited to ("English only"), or null.
 *
 * What the conditions limit the pages to is said by the one value they keep, when they keep one: the page itself
 * when the value is a page ("Contact"), else how many pages have the value ("146 Simple Product"). Other conditions
 * say how many pages they reach ("2 pages").
 *
 * Each kind's label, glyph and description are the admin pages' own (see KindLabels).
 *
 * @phpstan-import-type Target from ScreenLinks
 * @phpstan-type Chip array{id: int|null, code: string, name: string, on: bool, sub: string|null}
 * @phpstan-type Row array{kind: string, label: string, icon: string, pages: string,
 *     see: Target|null, empty: string|null, snippets: list<Chip>}
 */
class KindRows
{
    private const TEST_ROUTE = 'hryvinskyi_richsnippets/test/index';
    private const HOME = 'home';
    private const TEST_PERMISSION = 'test';

    /**
     * @param WordList $words
     * @param StoreNames $stores
     * @param ScreenLinks $links
     * @param KindLabels $labels
     * @param PagePermissionsInterface $permissions what the signed-in admin may do on the module's pages
     * @param LimitingValue $limitingValue
     * @param CountWords $counts
     */
    public function __construct(
        private readonly WordList $words,
        private readonly StoreNames $stores,
        private readonly ScreenLinks $links,
        private readonly KindLabels $labels,
        private readonly PagePermissionsInterface $permissions,
        private readonly LimitingValue $limitingValue,
        private readonly CountWords $counts
    ) {
    }

    /**
     * The rows
     *
     * @param OverviewReportInterface $report
     * @return list<Row>
     */
    public function rows(OverviewReportInterface $report): array
    {
        $showsPages = $report->getSetupState() === SetupState::Live
            && ($this->permissions->granted()[self::TEST_PERMISSION] ?? false);
        $storeId = $report->getStoreId();
        $rows = [];
        foreach ($report->getKinds() as $kind) {
            $code = $kind->getKind();
            $count = $kind->getPageCount();
            $listable = $count !== null;
            $rows[] = [
                'kind' => $code,
                'label' => $this->labels->label($code),
                'icon' => $this->labels->icon($code),
                'pages' => $this->pages($kind),
                'see' => $showsPages && $count !== null && $count > 0 ? $this->see($kind, $storeId) : null,
                'empty' => $kind->getSnippets() !== [] ? null : ($listable
                    ? (string) __('Nothing.')
                    : (string) __('Nothing. No snippet is set for these pages.')),
                'snippets' => array_map(
                    fn (KindSnippetInterface $set): array => $this->chip($set, $code, $storeId),
                    $kind->getSnippets()
                ),
            ];
        }

        return $rows;
    }

    /**
     * The kind's pages in words: "184 pages", or its description when they cannot be listed
     *
     * @param KindOverviewInterface $kind
     * @return string
     */
    private function pages(KindOverviewInterface $kind): string
    {
        $count = $kind->getPageCount();
        if ($count === null) {
            return $this->labels->description($kind->getKind());
        }

        return $this->counts->pages($count);
    }

    /**
     * The Test page showing one page of the kind, named as the page picker names it (`product:12`, `home`)
     *
     * @param KindOverviewInterface $kind
     * @param int|null $storeId
     * @return Target
     */
    private function see(KindOverviewInterface $kind, ?int $storeId): array
    {
        $code = $kind->getKind();
        $id = $kind->getSampleEntityId();

        return $this->links->target(
            self::TEST_ROUTE,
            ['page' => $code === self::HOME || $id === null ? $code : $code . ':' . $id],
            $storeId
        );
    }

    /**
     * One snippet's chip
     *
     * @param KindSnippetInterface $set
     * @param string $kind
     * @param int|null $storeId the store view the map is of; null for every store view
     * @return Chip
     */
    private function chip(KindSnippetInterface $set, string $kind, ?int $storeId): array
    {
        $snippet = $set->getSnippet();

        return [
            'id' => $snippet->getId(),
            'code' => $snippet->getCode(),
            'name' => $snippet->getName(),
            'on' => $snippet->isActive(),
            'sub' => $snippet->isActive() ? $this->sub($set, $kind, $storeId) : (string) __('off'),
        ];
    }

    /**
     * What a switched-on chip says under its name: "146 Simple Product, English only"; null when it says nothing
     *
     * @param KindSnippetInterface $set
     * @param string $kind
     * @param int|null $storeId
     * @return string|null
     */
    private function sub(KindSnippetInterface $set, string $kind, ?int $storeId): ?string
    {
        $parts = [];
        $count = $set->getPlacementCount();
        if ($count !== null) {
            $parts[] = $this->limitedTo($set->getSnippet(), $kind, $storeId, $count->getMatching());
        }
        $own = $set->getSnippet()->getStoreIds();
        if ($storeId === null && $own !== [SnippetInterface::ALL_STORES]) {
            $parts[] = (string) __(
                '%1 only',
                $this->words->join(array_map(fn (int $id): string => $this->stores->name($id), $own))
            );
        }

        return $parts === [] ? null : $this->words->series($parts);
    }

    /**
     * What the snippet's conditions limit the kind's pages to
     *
     * That is the page the one value they keep is, the pages that have that value, or how many pages they reach.
     *
     * @param SnippetInterface $snippet
     * @param string $kind
     * @param int|null $storeId
     * @param int $matching the pages of the kind that meet the conditions
     * @return string
     */
    private function limitedTo(SnippetInterface $snippet, string $kind, ?int $storeId, int $matching): string
    {
        $value = $this->limitingValue->of($snippet->getConditions(), $kind, $storeId);
        if ($value !== null && $value['page']) {
            return $value['label'];
        }
        if ($value !== null) {
            return (string) __('%1 %2', $matching, $value['label']);
        }

        return $this->counts->pages($matching);
    }
}
