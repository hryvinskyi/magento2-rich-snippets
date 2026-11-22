<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Field\Attribute;

use Hryvinskyi\RichSnippets\Api\AttributeValueResolverInterface;
use Hryvinskyi\RichSnippets\Model\Price\PriceDisplay;

/**
 * An amount attribute (a price other than the product's own) in the visitor's display currency, as a string at
 * the platform's price precision
 */
class PriceValue implements AttributeValueResolverInterface
{
    /**
     * @param PriceDisplay $display
     */
    public function __construct(private readonly PriceDisplay $display)
    {
    }

    /**
     * @inheritDoc
     */
    public function resolve(mixed $value, array $optionLabels, int $storeId): mixed
    {
        return is_numeric($value) ? $this->display->money((float) $value, $storeId) : null;
    }
}
