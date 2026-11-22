<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Data;

use Hryvinskyi\RichSnippets\Api\EntityDataSourceInterface;

/**
 * An entity's attribute values from a source's attribute part, sure to hold the attribute asked for
 *
 * The part loads the attributes the named variable paths read. An attribute read without being named (a template
 * the caller did not list) is named afterwards, which has the source load the part again with it, for the whole
 * batch; nothing is ever missed, it only costs a reload.
 */
class AttributeEntries
{
    /**
     * The entity's entry: `values` by code and `labels` (option label by stored value, by code)
     *
     * @param EntityDataSourceInterface $source
     * @param string $part the source's part holding the attribute values, with the `codes` it loaded
     * @param string $root the root whose paths name the attributes (`product`)
     * @param string $code
     * @param int $entityId
     * @param int $storeId
     * @return array{values: array<string, mixed>, labels: array<string, mixed>}|null null when the entity does not
     *     exist
     */
    public function entry(
        EntityDataSourceInterface $source,
        string $part,
        string $root,
        string $code,
        int $entityId,
        int $storeId
    ): ?array {
        $entry = $source->get($entityId, $storeId, $part);
        if (is_array($entry) && !in_array($code, is_array($entry['codes'] ?? null) ? $entry['codes'] : [], true)) {
            $source->prefetch([$entityId], $storeId, [$root . '.' . $code]);
            $entry = $source->get($entityId, $storeId, $part);
        }
        if (!is_array($entry)) {
            return null;
        }

        return [
            'values' => $this->keyed($entry['values'] ?? null),
            'labels' => $this->keyed($entry['labels'] ?? null),
        ];
    }

    /**
     * An array keyed by text; empty for anything else
     *
     * @param mixed $value
     * @return array<string, mixed>
     */
    private function keyed(mixed $value): array
    {
        $keyed = [];
        foreach (is_array($value) ? $value : [] as $key => $item) {
            $keyed[(string) $key] = $item;
        }

        return $keyed;
    }
}
