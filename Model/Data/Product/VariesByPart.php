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

/**
 * What a configurable product's variants differ by, under `varies_by`: one schema.org property address per
 * configurable attribute, named by the attribute's code (`https://schema.org/color`), in the product's order
 *
 * A product that is not configurable holds nothing.
 */
class VariesByPart implements EntityDataPartInterface
{
    private const KEY = 'varies_by';
    private const SCHEMA = 'https://schema.org/';

    /**
     * @param ConfigurableAttributes $configurable
     */
    public function __construct(private readonly ConfigurableAttributes $configurable)
    {
    }

    /**
     * @inheritDoc
     */
    public function load(array $ids, int $storeId, array $variablePaths, array $collectionLimits): array
    {
        $entries = [];
        foreach ($this->configurable->byProduct($ids) as $productId => $attributes) {
            $entries[$productId] = [
                self::KEY => array_map(
                    static fn (string $code): string => self::SCHEMA . $code,
                    array_map('strval', array_keys($attributes))
                ),
            ];
        }

        return $entries;
    }
}
