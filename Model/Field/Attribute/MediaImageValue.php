<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Field\Attribute;

use Hryvinskyi\RichSnippets\Api\AttributeValueResolverInterface;
use Hryvinskyi\RichSnippets\Model\Store\StoreAddresses;

/**
 * A product image as the absolute address of its file in the store view; no image chosen is nothing
 */
class MediaImageValue implements AttributeValueResolverInterface
{
    private const NO_SELECTION = 'no_selection';
    private const PRODUCT_MEDIA = 'catalog/product/';

    /**
     * @param StoreAddresses $addresses
     */
    public function __construct(private readonly StoreAddresses $addresses)
    {
    }

    /**
     * @inheritDoc
     */
    public function resolve(mixed $value, array $optionLabels, int $storeId): mixed
    {
        if (!is_string($value) || $value === '' || $value === self::NO_SELECTION) {
            return null;
        }

        return $this->addresses->media(self::PRODUCT_MEDIA . ltrim($value, '/'), $storeId);
    }
}
