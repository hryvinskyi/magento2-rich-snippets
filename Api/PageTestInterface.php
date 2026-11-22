<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Api;

use Hryvinskyi\RichSnippets\Api\Data\PageEntityInterface;
use Hryvinskyi\RichSnippets\Api\Data\PageTestResultInterface;

/**
 * Tests one storefront page as a visitor gets it: reads it from the storefront, lists its JSON-LD blocks against
 * Google's requirements, notes the theme's own microdata, and says why each other snippet is not there
 *
 * The page is a page of the shop or a path; never a full address, so the host is always the store view's own. The
 * reasons are worked out as the storefront of the store view works them out, for a guest. Each test that reads a
 * page keeps what it saw of the theme's microdata for the page's store view and kind.
 *
 * @api
 */
interface PageTestInterface
{
    /**
     * Test a page of the shop: a product, a category, a CMS page or the home page
     *
     * @param PageEntityInterface $page
     * @return PageTestResultInterface
     * @throws \InvalidArgumentException when the page does not exist in its store view
     */
    public function testPage(PageEntityInterface $page): PageTestResultInterface;

    /**
     * Test the page at a path of a store view (`/checkout/cart`)
     *
     * @param string $path see StorefrontPathInterface
     * @param int $storeId
     * @return PageTestResultInterface
     * @throws \InvalidArgumentException when the path is not a storefront path
     */
    public function testPath(string $path, int $storeId): PageTestResultInterface;
}
