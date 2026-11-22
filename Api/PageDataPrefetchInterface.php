<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Api;

use Hryvinskyi\RichSnippets\Api\Data\PageEntityInterface;

/**
 * Names the pages about to be evaluated to every entity source that serves them, in one call
 *
 * Each page's entity goes to the source of its kind of page (a product page's product to the product source) and
 * each page's store view to the store source, so the evaluations that follow read their data in batches.
 *
 * @api
 */
interface PageDataPrefetchInterface
{
    /**
     * Name the pages to the sources
     *
     * @param list<PageEntityInterface> $pages
     * @param list<string> $variablePaths the variable paths the templates read
     * @param array<string,int> $collectionLimits the most items any template takes from a list, by the list's path
     * @return void
     */
    public function prefetch(array $pages, array $variablePaths, array $collectionLimits = []): void;

    /**
     * Forget everything every source has named and loaded
     *
     * @return void
     */
    public function reset(): void;
}
