<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Field\Resolver;

use Hryvinskyi\RichSnippets\Api\Data\FieldInterface;
use Hryvinskyi\RichSnippets\Api\EntityDataSourceInterface;
use Hryvinskyi\RichSnippets\Api\FieldResolverInterface;
use Hryvinskyi\RichSnippets\Model\Catalog\ProductAttributes;
use Hryvinskyi\RichSnippets\Model\Data\AttributeEntries;
use Hryvinskyi\RichSnippets\Model\Field\Attribute\AttributeValueResolverPool;

/**
 * A product attribute's value as a template reads it, by the resolver of the attribute's input type
 */
class ProductAttributeResolver implements FieldResolverInterface
{
    private const ROOT = 'product';

    /**
     * @param EntityDataSourceInterface $products
     * @param ProductAttributes $attributes
     * @param AttributeValueResolverPool $values
     * @param AttributeEntries $entries
     * @param string $part the product source's part holding the attribute values
     */
    public function __construct(
        private readonly EntityDataSourceInterface $products,
        private readonly ProductAttributes $attributes,
        private readonly AttributeValueResolverPool $values,
        private readonly AttributeEntries $entries,
        private readonly string $part = 'attributes'
    ) {
    }

    /**
     * @inheritDoc
     */
    public function resolve(FieldInterface $field, int $subjectId, int $storeId): mixed
    {
        $code = $field->getAttributeCode();
        if ($code === null) {
            return null;
        }
        $entry = $this->entries->entry($this->products, $this->part, self::ROOT, $code, $subjectId, $storeId);
        if ($entry === null) {
            return null;
        }
        $values = $entry['values'];
        $labels = is_array($entry['labels'][$code] ?? null) ? $entry['labels'][$code] : [];
        $optionLabels = [];
        foreach ($labels as $value => $label) {
            $optionLabels[$value] = is_scalar($label) ? (string) $label : '';
        }

        return $this->values->get($this->attributes->input($code))->resolve(
            $values[$code] ?? null,
            $optionLabels,
            $storeId
        );
    }
}
