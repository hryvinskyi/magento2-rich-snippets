<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Check\Label;

/**
 * Names the pages of one kind the way the admin knows them: a product by its name, SKU and attribute set, a category
 * by its name, a CMS page by its title
 *
 * @phpstan-type Label array{label: string, sku: string|null, group: string|null}
 */
interface PageLabelsInterface
{
    /**
     * The names of some pages' entities in a store view, read in one go
     *
     * @param list<int> $ids the entities the pages show; 0 for a page that shows none
     * @param int $storeId
     * @return array<int, Label> by entity; an entity that no longer exists is left out
     */
    public function labels(array $ids, int $storeId): array;
}
