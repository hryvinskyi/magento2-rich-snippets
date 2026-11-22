<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Api;

/**
 * The expression builder's configuration for the kinds of page a snippet is output on
 *
 * The builder offers the fields of the picked kinds, kind by kind in the kinds' configured order: for product pages
 * a product attribute, the attribute set, the category and the product type; for category pages the category page;
 * for CMS pages the CMS page; nothing for the home page or every other page.
 *
 * @api
 */
interface ConditionBuilderConfigInterface
{
    /**
     * The builder's configuration: `combineType`, `fields`, `operatorSets`, `emptyStateMessage`, `optionsUrl`,
     * `suggestUrl` and `suggestMinQuery`
     *
     * @param list<string> $kinds the kinds of page picked, by code
     * @param int $storeId the store view whose labels the options carry
     * @return array<string,mixed>
     * @throws \InvalidArgumentException when no kind has one of the codes
     */
    public function forKinds(array $kinds, int $storeId): array;
}
