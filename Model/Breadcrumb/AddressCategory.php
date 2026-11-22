<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Breadcrumb;

use Magento\Framework\App\Request\Http as HttpRequest;

/**
 * The category a product page's address runs through, when the current request is that product's page
 *
 * A product address under a category (`bags/field-satchel.html`) reaches the product page with the category as a
 * request parameter; the page cache keys pages by their address, so a trail through that category is the same for
 * every visitor of the address. Outside a storefront product page (the admin, cron) there is none.
 */
class AddressCategory
{
    private const PRODUCT_PAGE = 'catalog_product_view';

    /**
     * @param HttpRequest $request
     */
    public function __construct(private readonly HttpRequest $request)
    {
    }

    /**
     * The category the current request's address names for a product
     *
     * @param int $productId
     * @return int|null
     */
    public function categoryFor(int $productId): ?int
    {
        $id = $this->request->getParam('id');
        if ($this->request->getFullActionName() !== self::PRODUCT_PAGE
            || !is_numeric($id)
            || (int) $id !== $productId
        ) {
            return null;
        }
        $category = $this->request->getParam('category');

        return is_numeric($category) && (int) $category > 0 ? (int) $category : null;
    }
}
