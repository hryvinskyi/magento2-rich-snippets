<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Api\Data;

/**
 * One page of a finding, named as the admin knows it
 *
 * @api
 */
interface FindingItemInterface
{
    /**
     * The code of the page's kind
     *
     * @return string
     */
    public function getKind(): string;

    /**
     * The entity the page shows; null for a page that shows none
     *
     * @return int|null
     */
    public function getEntityId(): ?int;

    /**
     * A store view the page has the gap in, to test it there
     *
     * @return int
     */
    public function getStoreId(): int;

    /**
     * The page's name: the product's or category's name, the CMS page's title
     *
     * @return string
     */
    public function getLabel(): string;

    /**
     * The product's SKU; null for any other page
     *
     * @return string|null
     */
    public function getSku(): ?string;

    /**
     * What the page belongs to, such as a product's attribute set; null when nothing is shown
     *
     * @return string|null
     */
    public function getGroup(): ?string;
}
