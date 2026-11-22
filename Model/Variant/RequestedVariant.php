<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Variant;

use Magento\Catalog\Model\Product;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\DataObjectFactory;

/**
 * Preselects on a configurable product's page the variant its address names
 *
 * The options go into the product's preconfigured values, which the platform's configurable options already turn
 * into the selection the page opens with. The address must name an option of every configurable attribute, and the
 * combination must be a child that can be bought; otherwise nothing is preselected and nothing fails.
 */
class RequestedVariant
{
    /**
     * @param VariantAddress $address
     * @param DataObjectFactory $dataObjects
     */
    public function __construct(
        private readonly VariantAddress $address,
        private readonly DataObjectFactory $dataObjects
    ) {
    }

    /**
     * Preselect the variant the request names, when it is one
     *
     * @param Product $product
     * @param RequestInterface $request
     * @return void
     */
    public function preselect(Product $product, RequestInterface $request): void
    {
        $type = $product->getTypeInstance();
        if ($product->getTypeId() !== Configurable::TYPE_CODE || !$type instanceof Configurable) {
            return;
        }
        $attributeIds = [];
        foreach ($type->getConfigurableAttributes($product) as $attribute) {
            $attributeIds[(string) $attribute->getProductAttribute()->getAttributeCode()]
                = (int) $attribute->getAttributeId();
        }
        $options = $this->address->requested($request, array_map('strval', array_keys($attributeIds)));
        if ($options === null || !$this->isSalable($type, $product, $options)) {
            return;
        }
        $selection = [];
        foreach ($options as $code => $optionId) {
            $selection[$attributeIds[$code]] = (string) $optionId;
        }
        $product->setData(
            'preconfigured_values',
            $this->dataObjects->create(['data' => ['super_attribute' => $selection]])
        );
    }

    /**
     * Whether a child that can be bought carries every option
     *
     * @param Configurable $type
     * @param Product $product
     * @param array<string,int> $options option ID by attribute code
     * @return bool
     */
    private function isSalable(Configurable $type, Product $product, array $options): bool
    {
        foreach ($type->getUsedProducts($product) as $child) {
            if (!$child instanceof Product || !$child->isSalable()) {
                continue;
            }
            $matches = true;
            foreach ($options as $code => $optionId) {
                $value = $child->getData($code);
                $matches = $matches && is_numeric($value) && (int) $value === $optionId;
            }
            if ($matches) {
                return true;
            }
        }

        return false;
    }
}
