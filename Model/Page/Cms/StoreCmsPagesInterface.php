<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Page\Cms;

/**
 * The CMS pages a store view's configuration gives a role: the home page and the page-not-found page
 */
interface StoreCmsPagesInterface
{
    /**
     * The ID of the active CMS page the store view shows as its home page
     *
     * @param int $storeId
     * @return int|null null when the configured page does not exist, is inactive or is not in the store view
     */
    public function homePageId(int $storeId): ?int;

    /**
     * The ID of the active CMS page the store view shows when a page is not found
     *
     * @param int $storeId
     * @return int|null null when the configured page does not exist, is inactive or is not in the store view
     */
    public function noRoutePageId(int $storeId): ?int;
}
