<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Data\Product;

use Hryvinskyi\RichSnippets\Api\EntityDataPartInterface;
use Hryvinskyi\RichSnippets\Model\Catalog\ConfigurableAttributes;
use Hryvinskyi\RichSnippets\Model\Catalog\EavValueReader;
use Hryvinskyi\RichSnippets\Model\Catalog\EntityAddresses;
use Hryvinskyi\RichSnippets\Model\Catalog\OptionLabels;
use Hryvinskyi\RichSnippets\Model\Catalog\ProductAttributes;
use Hryvinskyi\RichSnippets\Model\Catalog\StockVisibility;
use Hryvinskyi\RichSnippets\Model\Field\Attribute\AttributeValueResolverPool;
use Hryvinskyi\RichSnippets\Model\Price\PriceDisplay;
use Hryvinskyi\RichSnippets\Model\Price\PriceIndex;
use Hryvinskyi\RichSnippets\Model\Store\StoreAddresses;
use Hryvinskyi\RichSnippets\Model\Variant\VariantAddress;

/**
 * Each configurable product's variants the store view offers, as list items: `sku`, `name`, `price` (as the visitor
 * sees it), `availability` (schema.org URL), `image` (absolute), `url` (the address that selects the variant) and
 * the label of each configurable attribute's option under the attribute's code (`color` => `Tan`)
 *
 * Any other product attribute a template reads from a variant (`variant.ean`) is the child's value under its code,
 * resolved by the attribute's input type as `product.<code>` is; a configurable attribute's option label and the
 * keys above win over an attribute of the same code.
 *
 * A variant is a child that is enabled and in the store view's website; out-of-stock children are offered only
 * when the store shows out-of-stock products. A child missing a value of a configurable attribute is no variant.
 */
class VariantsPart implements EntityDataPartInterface
{
    private const ROUTE = 'catalog/product/view/id/%d';
    private const REWRITE_TYPE = 'product';
    private const IN_STOCK = 'https://schema.org/InStock';
    private const OUT_OF_STOCK = 'https://schema.org/OutOfStock';
    private const NO_SELECTION = 'no_selection';
    private const PRODUCT_MEDIA = 'catalog/product/';
    private const ITEM = 'variant';
    private const OWN_KEYS = ['sku' => true, 'name' => true, 'price' => true, 'availability' => true, 'image' => true,
        'url' => true];
    private const OPTION_INPUTS = ['select' => true, 'multiselect' => true];

    /**
     * @param PriceIndex $index
     * @param PriceDisplay $display
     * @param ConfigurableAttributes $configurable
     * @param ProductAttributes $productAttributes
     * @param AttributeValueResolverPool $attributeValues
     * @param EavValueReader $reader the product attribute values
     * @param OptionLabels $optionLabels
     * @param EntityAddresses $entityAddresses
     * @param StoreAddresses $storeAddresses
     * @param VariantAddress $variantAddress
     * @param StockVisibility $stockVisibility
     */
    public function __construct(
        private readonly PriceIndex $index,
        private readonly PriceDisplay $display,
        private readonly ConfigurableAttributes $configurable,
        private readonly ProductAttributes $productAttributes,
        private readonly AttributeValueResolverPool $attributeValues,
        private readonly EavValueReader $reader,
        private readonly OptionLabels $optionLabels,
        private readonly EntityAddresses $entityAddresses,
        private readonly StoreAddresses $storeAddresses,
        private readonly VariantAddress $variantAddress,
        private readonly StockVisibility $stockVisibility
    ) {
    }

    /**
     * @inheritDoc
     */
    public function load(array $ids, int $storeId, array $variablePaths, array $collectionLimits): array
    {
        $children = $this->index->children($ids, $storeId);
        if ($children === []) {
            return [];
        }
        $showOutOfStock = $this->stockVisibility->showsOutOfStock($storeId);
        $attributes = $this->configurable->byProduct(array_keys($children));
        $read = $this->readCodes($variablePaths);
        $codes = ['name', 'image', ...$read];
        $childIds = [];
        foreach ($children as $list) {
            foreach ($list as $child) {
                $childIds[] = $child['child_id'];
            }
        }
        foreach ($attributes as $byCode) {
            $codes = [...$codes, ...array_keys($byCode)];
        }
        $values = $this->reader->values($childIds, array_values(array_unique($codes)), $storeId);
        $optionCodes = $this->tableOptionCodes($read);
        $optionIds = [];
        foreach ($values as $row) {
            foreach ($attributes as $byCode) {
                foreach (array_keys($byCode) as $code) {
                    if (is_numeric($row[$code] ?? null)) {
                        $optionIds[] = (int) $row[$code];
                    }
                }
            }
            foreach ($optionCodes as $code) {
                $stored = $row[$code] ?? null;
                foreach (is_scalar($stored) ? explode(',', (string) $stored) : [] as $optionId) {
                    if (is_numeric($optionId)) {
                        $optionIds[] = (int) $optionId;
                    }
                }
            }
        }
        $labels = $this->optionLabels->labels($optionIds, $storeId);
        $urls = $this->entityAddresses->urls(self::REWRITE_TYPE, self::ROUTE, array_keys($children), $storeId);

        $entries = [];
        foreach ($children as $parentId => $list) {
            foreach ($list as $child) {
                if (!$child['in_stock'] && !$showOutOfStock) {
                    continue;
                }
                $item = $this->variant(
                    $child,
                    $values[$child['child_id']] ?? [],
                    $attributes[$parentId] ?? [],
                    $labels,
                    $urls[$parentId],
                    $storeId
                );
                if ($item !== null) {
                    $item += $this->readValues($values[$child['child_id']] ?? [], $read, $labels, $storeId);
                }
                if ($item !== null) {
                    $entries[$parentId][] = $item;
                }
            }
        }

        return $entries;
    }

    /**
     * One variant's item; null when the child misses a configurable attribute's option
     *
     * @param array{child_id:int,sku:string,final_price:float,tax_class_id:int,in_stock:bool} $child
     * @param array<string,mixed> $values the child's attribute values
     * @param array<string,int> $attributes the configurable attributes' IDs by code, in the product's order
     * @param array<int,string> $labels option labels by option ID
     * @param string $productUrl
     * @param int $storeId
     * @return array<string, mixed>|null
     */
    private function variant(
        array $child,
        array $values,
        array $attributes,
        array $labels,
        string $productUrl,
        int $storeId
    ): ?array {
        if ($attributes === []) {
            return null;
        }
        $options = [];
        $optionLabels = [];
        foreach (array_keys($attributes) as $code) {
            $optionId = $values[$code] ?? null;
            if (!is_numeric($optionId)) {
                return null;
            }
            $options[$code] = (int) $optionId;
            $optionLabels[$code] = $labels[(int) $optionId] ?? null;
        }
        $image = $values['image'] ?? null;

        return [
            'sku' => $child['sku'],
            'name' => is_string($values['name'] ?? null) ? $values['name'] : null,
            'price' => $this->display->price($child['final_price'], $child['tax_class_id'], $storeId),
            'availability' => $child['in_stock'] ? self::IN_STOCK : self::OUT_OF_STOCK,
            'image' => is_string($image) && $image !== '' && $image !== self::NO_SELECTION
                ? $this->storeAddresses->media(self::PRODUCT_MEDIA . ltrim($image, '/'), $storeId)
                : null,
            'url' => $this->variantAddress->url($productUrl, $options),
        ] + $optionLabels;
    }

    /**
     * The product attribute codes the templates read from a variant, other than the item's own keys
     *
     * @param list<string> $variablePaths
     * @return list<string>
     */
    private function readCodes(array $variablePaths): array
    {
        $codes = [];
        foreach ($variablePaths as $path) {
            $segments = explode('.', $path);
            $code = $segments[1] ?? '';
            if ($segments[0] === self::ITEM && !isset(self::OWN_KEYS[$code])
                && $this->productAttributes->has($code) && !$this->productAttributes->isStatic($code)
            ) {
                $codes[$code] = true;
            }
        }

        return array_map('strval', array_keys($codes));
    }

    /**
     * The codes among the read ones whose options are kept in the option tables
     *
     * @param list<string> $codes
     * @return list<string>
     */
    private function tableOptionCodes(array $codes): array
    {
        return array_values(array_filter(
            $codes,
            fn (string $code): bool => isset(self::OPTION_INPUTS[$this->productAttributes->input($code)])
                && $this->productAttributes->optionsInTables($code)
        ));
    }

    /**
     * A child's values of the read attributes, each resolved by its input type
     *
     * @param array<string,mixed> $values the child's attribute values
     * @param list<string> $codes
     * @param array<int,string> $labels option labels from the option tables, by option ID
     * @param int $storeId
     * @return array<string, mixed>
     */
    private function readValues(array $values, array $codes, array $labels, int $storeId): array
    {
        $resolved = [];
        foreach ($codes as $code) {
            $input = $this->productAttributes->input($code);
            $optionLabels = !isset(self::OPTION_INPUTS[$input]) ? [] : (
                $this->productAttributes->optionsInTables($code)
                    ? $labels
                    : $this->productAttributes->sourceOptionLabels($code, $storeId)
            );
            $resolved[$code] = $this->attributeValues->get($input)
                ->resolve($values[$code] ?? null, $optionLabels, $storeId);
        }

        return $resolved;
    }
}
