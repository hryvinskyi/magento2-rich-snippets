<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Catalog;

use Magento\Catalog\Model\ResourceModel\Eav\Attribute;
use Magento\Catalog\Model\ResourceModel\Product\Attribute\CollectionFactory;
use Magento\Eav\Model\Entity\Attribute\Source\Table;
use Magento\Framework\ObjectManager\ResetAfterRequestInterface;

/**
 * The product attributes: input type, label, where their options are kept, read once per request
 *
 * What is read is dropped after each request, so a process that serves many requests sees an attribute added
 * meanwhile.
 *
 * Options of a select or multiselect are either kept in the option tables (labels read in bulk) or supplied by a
 * source model in code, whose labels are asked once per attribute and store view.
 */
class ProductAttributes implements ResetAfterRequestInterface
{
    private const STATIC_BACKEND = 'static';

    /**
     * @var array<string, Attribute>|null by code
     */
    private ?array $attributes = null;

    /**
     * @var array<string, array<int|string, string>> the labels of source-model options, by store view and code
     */
    private array $sourceLabels = [];

    /**
     * @param CollectionFactory $collections
     */
    public function __construct(private readonly CollectionFactory $collections)
    {
    }

    /**
     * Every product attribute code, in the order of their labels
     *
     * @return list<string>
     */
    public function codes(): array
    {
        return array_map('strval', array_keys($this->attributes()));
    }

    /**
     * Whether the product entity has an attribute
     *
     * @param string $code
     * @return bool
     */
    public function has(string $code): bool
    {
        return isset($this->attributes()[$code]);
    }

    /**
     * The attribute's ID; 0 when it does not exist
     *
     * @param string $code
     * @return int
     */
    public function id(string $code): int
    {
        return (int) ($this->attributes()[$code] ?? null)?->getAttributeId();
    }

    /**
     * Whether the attribute is a column of the product entity table rather than a value table of its own
     *
     * @param string $code
     * @return bool
     */
    public function isStatic(string $code): bool
    {
        return ($this->attributes()[$code] ?? null)?->getBackendType() === self::STATIC_BACKEND;
    }

    /**
     * The attribute's input type (`text`, `select` …); empty when it does not exist
     *
     * @param string $code
     * @return string
     */
    public function input(string $code): string
    {
        return (string) ($this->attributes()[$code] ?? null)?->getFrontendInput();
    }

    /**
     * The attribute's admin label, its code when it has none
     *
     * @param string $code
     * @return string
     */
    public function label(string $code): string
    {
        $label = (string) ($this->attributes()[$code] ?? null)?->getDefaultFrontendLabel();

        return $label !== '' ? $label : $code;
    }

    /**
     * Whether the attribute's options are kept in the option tables
     *
     * @param string $code
     * @return bool
     */
    public function optionsInTables(string $code): bool
    {
        $attribute = $this->attributes()[$code] ?? null;
        if ($attribute === null) {
            return false;
        }
        $source = $attribute->getData('source_model');

        return !is_string($source) || $source === '' || is_a($source, Table::class, true);
    }

    /**
     * The labels of the options a source model supplies, in a store view
     *
     * @param string $code
     * @param int $storeId
     * @return array<int|string, string> by option value
     */
    public function sourceOptionLabels(string $code, int $storeId): array
    {
        $key = $storeId . ':' . $code;
        if (isset($this->sourceLabels[$key])) {
            return $this->sourceLabels[$key];
        }
        $attribute = $this->attributes()[$code] ?? null;
        $labels = [];
        if ($attribute !== null && $attribute->usesSource()) {
            $attribute->setData('store_id', $storeId);
            foreach ($attribute->getSource()->getAllOptions() as $option) {
                $value = is_array($option) ? ($option['value'] ?? null) : null;
                $label = is_array($option) ? ($option['label'] ?? null) : null;
                $label = is_scalar($label) || $label instanceof \Stringable ? (string) $label : '';
                if (is_scalar($value) && (string) $value !== '' && $label !== '') {
                    $labels[(string) $value] = $label;
                }
            }
        }

        return $this->sourceLabels[$key] = $labels;
    }

    /**
     * The attributes by code
     *
     * @return array<string, Attribute>
     */
    private function attributes(): array
    {
        if ($this->attributes !== null) {
            return $this->attributes;
        }
        $attributes = [];
        foreach ($this->collections->create()->setOrder('frontend_label', 'ASC')->getItems() as $attribute) {
            if ($attribute instanceof Attribute) {
                $attributes[(string) $attribute->getAttributeCode()] = $attribute;
            }
        }

        return $this->attributes = $attributes;
    }

    /**
     * @inheritDoc
     */
    public function _resetState(): void
    {
        $this->attributes = null;
        $this->sourceLabels = [];
    }
}
