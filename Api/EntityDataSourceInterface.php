<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Api;

/**
 * Reads one kind of entity (products, categories, CMS pages, store views) for the snippets, in batches
 *
 * The storefront, the editor's preview and the catalog check read through the same source. The caller names the
 * entities it will read and the variable paths its templates read; every part of the data (attribute values,
 * prices, stock, reviews, images, addresses …) is then loaded for all those entities at once, the first time any
 * of them is read, with a fixed number of queries per part whatever the number of entities. An entity read without
 * being named first is loaded on its own. What was loaded is kept until `reset()`, so a long run drops it between
 * batches.
 *
 * @api
 */
interface EntityDataSourceInterface
{
    /**
     * Name the entities of a store view the next reads will ask for
     *
     * Several calls add up. A call that names a variable path not named before for the store view has its parts
     * loaded again on the next read, so they cover it; the collection limits of the latest call apply.
     *
     * @param list<int> $ids
     * @param int $storeId
     * @param list<string> $variablePaths the dot-separated variable paths the templates read (`product.ean`)
     * @param array<string,int> $collectionLimits the most items any template takes from a list, by the list's path
     *     (`reviews.latest` => 3); a list not named here is read whole
     * @return void
     */
    public function prefetch(array $ids, int $storeId, array $variablePaths, array $collectionLimits = []): void;

    /**
     * One part of an entity's data in a store view, loading that part for every named entity on first read
     *
     * @param int $entityId
     * @param int $storeId
     * @param string $part the part's code (`attributes`, `price`, `gallery` …)
     * @return mixed what the part holds for the entity; null when it holds nothing
     * @throws \OutOfBoundsException when the source has no part with this code
     */
    public function get(int $entityId, int $storeId, string $part): mixed;

    /**
     * Forget every named entity and everything loaded
     *
     * @return void
     */
    public function reset(): void;
}
