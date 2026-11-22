<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Data\Product;

use Hryvinskyi\RichSnippets\Api\EntityDataPartInterface;
use Hryvinskyi\RichSnippets\Model\Catalog\EavValueReader;
use Hryvinskyi\RichSnippets\Model\Catalog\OptionLabels;
use Hryvinskyi\RichSnippets\Model\Catalog\ProductAttributes;

/**
 * The stored values of the product attributes the templates read (`product.<code>`), with the labels of the select
 * and multiselect options each product holds
 *
 * Each product's entry is `values` (stored value by attribute code, the entity table's columns included),
 * `labels` (option label by stored value, by attribute code) and `codes` (the attributes loaded).
 */
class AttributeValuesPart implements EntityDataPartInterface
{
    private const ROOT = 'product';
    private const OPTION_INPUTS = ['select' => true, 'multiselect' => true];

    /**
     * @param EavValueReader $reader the product attribute values
     * @param ProductAttributes $attributes
     * @param OptionLabels $optionLabels
     */
    public function __construct(
        private readonly EavValueReader $reader,
        private readonly ProductAttributes $attributes,
        private readonly OptionLabels $optionLabels
    ) {
    }

    /**
     * @inheritDoc
     */
    public function load(array $ids, int $storeId, array $variablePaths, array $collectionLimits): array
    {
        $codes = $this->codes($variablePaths);
        $values = $this->reader->values($ids, $codes, $storeId);
        $tableOptions = [];
        foreach ($values as $row) {
            foreach ($this->optionCodes($codes) as $code => $inTables) {
                if ($inTables) {
                    foreach ($this->stored($row[$code] ?? null) as $optionId) {
                        $tableOptions[(int) $optionId] = true;
                    }
                }
            }
        }
        $tableLabels = $this->optionLabels->labels(array_keys($tableOptions), $storeId);

        $entries = [];
        foreach ($values as $id => $row) {
            $labels = [];
            foreach ($this->optionCodes($codes) as $code => $inTables) {
                $known = $inTables ? [] : $this->attributes->sourceOptionLabels($code, $storeId);
                foreach ($this->stored($row[$code] ?? null) as $option) {
                    $label = $inTables ? ($tableLabels[(int) $option] ?? null) : ($known[$option] ?? null);
                    if ($label !== null) {
                        $labels[$code][$option] = $label;
                    }
                }
            }
            $entries[$id] = ['values' => $row, 'labels' => $labels, 'codes' => $codes];
        }

        return $entries;
    }

    /**
     * The attribute codes the paths read
     *
     * @param list<string> $paths
     * @return list<string>
     */
    private function codes(array $paths): array
    {
        $codes = [];
        foreach ($paths as $path) {
            $segments = explode('.', $path);
            if ($segments[0] === self::ROOT && isset($segments[1]) && $this->attributes->has($segments[1])) {
                $codes[$segments[1]] = true;
            }
        }

        return array_map('strval', array_keys($codes));
    }

    /**
     * The codes of select and multiselect attributes, each with whether its options are kept in the option tables
     *
     * @param list<string> $codes
     * @return array<string, bool>
     */
    private function optionCodes(array $codes): array
    {
        $options = [];
        foreach ($codes as $code) {
            if (isset(self::OPTION_INPUTS[$this->attributes->input($code)])) {
                $options[$code] = $this->attributes->optionsInTables($code);
            }
        }

        return $options;
    }

    /**
     * The option values a stored value holds
     *
     * @param mixed $value
     * @return list<string>
     */
    private function stored(mixed $value): array
    {
        if (!is_scalar($value) || (string) $value === '') {
            return [];
        }

        return array_values(array_filter(
            array_map('trim', explode(',', (string) $value)),
            static fn (string $option): bool => $option !== ''
        ));
    }
}
