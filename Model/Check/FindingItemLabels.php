<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Check;

use Hryvinskyi\RichSnippets\Api\Data\FindingItemInterface;
use Hryvinskyi\RichSnippets\Api\Data\FindingItemInterfaceFactory;
use Hryvinskyi\RichSnippets\Model\Check\Label\PageLabelPool;

/**
 * Turns the stored pages of a finding into the pages the admin reads: named, in the order they were given
 *
 * The names of each kind and store view come in one go. A page whose entity no longer exists keeps its place,
 * named by its kind and ID.
 *
 * @phpstan-import-type ItemRow from CheckResultReaderInterface
 */
class FindingItemLabels
{
    /**
     * @param PageLabelPool $labels
     * @param FindingItemInterfaceFactory $items
     */
    public function __construct(
        private readonly PageLabelPool $labels,
        private readonly FindingItemInterfaceFactory $items
    ) {
    }

    /**
     * The pages, named
     *
     * @param list<ItemRow> $rows
     * @return list<FindingItemInterface>
     */
    public function items(array $rows): array
    {
        $wanted = [];
        foreach ($rows as $row) {
            $wanted[$row['kind']][$row['store']][] = $row['entity'];
        }
        $named = [];
        foreach ($wanted as $kind => $byStore) {
            foreach ($byStore as $storeId => $ids) {
                $named[$kind][$storeId] = $this->labels->forKind((string) $kind)->labels($ids, (int) $storeId);
            }
        }

        return array_map(
            function (array $row) use ($named): FindingItemInterface {
                $label = $named[$row['kind']][$row['store']][$row['entity']] ?? null;

                return $this->items->create([
                    'kind' => $row['kind'],
                    'entityId' => $row['entity'] === 0 ? null : $row['entity'],
                    'storeId' => $row['store'],
                    'label' => $label['label'] ?? sprintf('%s %d', $row['kind'], $row['entity']),
                    'sku' => $label['sku'] ?? null,
                    'group' => $label['group'] ?? null,
                ]);
            },
            $rows
        );
    }
}
