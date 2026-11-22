<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Data;

use Hryvinskyi\RichSnippets\Api\EntityDataPartInterface;
use Hryvinskyi\RichSnippets\Model\Catalog\EavValueReader;

/**
 * The stored values of the attributes a root's paths read (`category.<code>`): each entity's entry is `values`
 * (stored value by code, the entity table's columns included), `labels` (none) and `codes` (the attributes loaded)
 *
 * Codes the entity type does not have are ignored by the reader.
 */
class EavValuesPart implements EntityDataPartInterface
{
    /**
     * @param EavValueReader $reader
     * @param string $root the root whose paths name the attributes (`category`)
     */
    public function __construct(
        private readonly EavValueReader $reader,
        private readonly string $root
    ) {
    }

    /**
     * @inheritDoc
     */
    public function load(array $ids, int $storeId, array $variablePaths, array $collectionLimits): array
    {
        $codes = [];
        foreach ($variablePaths as $path) {
            $segments = explode('.', $path);
            if ($segments[0] === $this->root && isset($segments[1])) {
                $codes[$segments[1]] = true;
            }
        }

        $codes = array_map('strval', array_keys($codes));
        $entries = [];
        foreach ($this->reader->values($ids, $codes, $storeId) as $id => $values) {
            $entries[$id] = ['values' => $values, 'labels' => [], 'codes' => $codes];
        }

        return $entries;
    }
}
