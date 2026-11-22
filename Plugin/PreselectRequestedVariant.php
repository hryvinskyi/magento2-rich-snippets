<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Plugin;

use Hryvinskyi\RichSnippets\Model\Variant\RequestedVariant;
use Magento\Catalog\Helper\Product as ProductHelper;
use Magento\Catalog\Model\Product;
use Magento\Framework\App\RequestInterface;

/**
 * After the storefront prepares a product for its page, preselect the variant the page's address names
 */
class PreselectRequestedVariant
{
    /**
     * @param RequestedVariant $variant
     * @param RequestInterface $request
     */
    public function __construct(
        private readonly RequestedVariant $variant,
        private readonly RequestInterface $request
    ) {
    }

    /**
     * Preselect the requested variant of the prepared product
     *
     * @param ProductHelper $subject
     * @param Product|false $result
     * @return Product|false
     */
    public function afterInitProduct(ProductHelper $subject, $result)
    {
        if ($result instanceof Product) {
            $this->variant->preselect($result, $this->request);
        }

        return $result;
    }
}
