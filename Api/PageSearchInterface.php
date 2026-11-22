<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Api;

use Hryvinskyi\RichSnippets\Api\Data\ConditionTreeInterface;
use Hryvinskyi\RichSnippets\Api\Data\PageEntityInterface;
use Hryvinskyi\RichSnippets\Api\Data\PageMatchInterface;

/**
 * Finds the pages a store view has, for a picker: products by name or SKU, categories and CMS pages by name, the home
 * page
 *
 * Only pages that exist are found: the same pages the placement counts and the catalog check count. One source per
 * kind of page answers; a kind without one has no pages to pick.
 *
 * @api
 */
interface PageSearchInterface
{
    /**
     * The pages of some kinds whose name holds a text, kind by kind in the order asked, each kind by name
     *
     * @param string $query matched anywhere in the name (and a product's SKU), case-insensitively; empty for any page
     * @param int $storeId
     * @param list<string> $kinds the kinds of page to look in
     * @param int $limit the most pages of each kind
     * @return list<PageMatchInterface>
     * @throws \InvalidArgumentException when no kind of page has one of the codes, or the limit is below 1
     */
    public function search(string $query, int $storeId, array $kinds, int $limit): array;

    /**
     * The pages of some kinds whose name holds a text, the pages that meet some conditions first
     *
     * First the pages each kind has that meet the conditions, kind by kind in the order asked, each kind by name; then
     * the other pages of the kinds, in the same order. A kind the conditions ask nothing of meets them with every
     * page, as a placement does. Each kind gives at most the limit, the pages that meet the conditions first.
     *
     * @param string $query matched anywhere in the name (and a product's SKU), case-insensitively; empty for any page
     * @param int $storeId
     * @param list<string> $kinds the kinds of page to look in
     * @param int $limit the most pages of each kind
     * @param ConditionTreeInterface $conditions the conditions the pages that come first meet
     * @return list<PageMatchInterface>
     * @throws \InvalidArgumentException when no kind of page has one of the codes, or the limit is below 1
     */
    public function searchMeeting(
        string $query,
        int $storeId,
        array $kinds,
        int $limit,
        ConditionTreeInterface $conditions
    ): array;

    /**
     * One page as a picker lists it
     *
     * @param PageEntityInterface $page
     * @return PageMatchInterface|null null when the page does not exist in its store view
     */
    public function find(PageEntityInterface $page): ?PageMatchInterface;
}
