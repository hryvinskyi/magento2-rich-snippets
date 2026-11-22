<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Api\Data;

/**
 * A page of the shop found by a search, as a picker lists it
 *
 * @api
 */
interface PageMatchInterface
{
    /**
     * The page
     *
     * @return PageEntityInterface
     */
    public function getPage(): PageEntityInterface;

    /**
     * What the picker shows: the product's, category's or CMS page's name in the store view
     *
     * @return string
     */
    public function getLabel(): string;

    /**
     * The page's absolute canonical address on the store view's storefront
     *
     * @return string
     */
    public function getUrl(): string;
}
