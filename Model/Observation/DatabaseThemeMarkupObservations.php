<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Observation;

use Hryvinskyi\RichSnippets\Api\Data\MicrodataItemInterface;
use Hryvinskyi\RichSnippets\Api\Data\MicrodataItemInterfaceFactory;
use Hryvinskyi\RichSnippets\Api\Data\ThemeMarkupObservationInterface;
use Hryvinskyi\RichSnippets\Api\Data\ThemeMarkupObservationInterfaceFactory;
use Hryvinskyi\RichSnippets\Api\ThemeMarkupObservationsInterface;
use Hryvinskyi\RichSnippets\Model\Storefront\ThemeMarkupTypes;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Serialize\SerializerInterface;

/**
 * Keeps the observations in a table with one row per store view and kind of page, replaced in one statement
 *
 * The items are kept as a JSON list of `{"type", "properties"}`; the time in UTC. An item that no longer reads as
 * one (a type left empty), or that is of a type the theme's markup is not checked for, is dropped when the row is
 * read, so a row kept before the check named its types reads as a page test notes a page now.
 */
class DatabaseThemeMarkupObservations implements ThemeMarkupObservationsInterface
{
    private const TABLE = 'hryvinskyi_rich_snippets_observation';
    private const TIME_FORMAT = 'Y-m-d H:i:s';

    /**
     * @param ResourceConnection $resource
     * @param SerializerInterface $json
     * @param ThemeMarkupObservationInterfaceFactory $observations
     * @param MicrodataItemInterfaceFactory $items
     * @param ThemeMarkupTypes $types
     */
    public function __construct(
        private readonly ResourceConnection $resource,
        private readonly SerializerInterface $json,
        private readonly ThemeMarkupObservationInterfaceFactory $observations,
        private readonly MicrodataItemInterfaceFactory $items,
        private readonly ThemeMarkupTypes $types
    ) {
    }

    /**
     * @inheritDoc
     */
    public function record(ThemeMarkupObservationInterface $observation): void
    {
        $items = array_map(
            static fn (MicrodataItemInterface $item): array => [
                'type' => $item->getType(),
                'properties' => $item->getProperties(),
            ],
            $observation->getItems()
        );
        $this->resource->getConnection()->insertOnDuplicate(
            $this->resource->getTableName(self::TABLE),
            [
                'store_id' => $observation->getStoreId(),
                'kind' => $observation->getKind(),
                'found' => (int) $observation->isFound(),
                'properties' => (string) $this->json->serialize($items),
                'path' => $observation->getPath(),
                'seen_at' => gmdate(self::TIME_FORMAT, $observation->getSeenAt()),
            ],
            ['found', 'properties', 'path', 'seen_at']
        );
    }

    /**
     * @inheritDoc
     */
    public function latest(int $storeId, string $kind): ?ThemeMarkupObservationInterface
    {
        $connection = $this->resource->getConnection();
        $row = $connection->fetchRow(
            $connection->select()
                ->from($this->resource->getTableName(self::TABLE), ['properties', 'path', 'seen_at'])
                ->where('store_id = ?', $storeId)
                ->where('kind = ?', $kind)
        );
        if (!is_array($row) || !is_string($row['path'] ?? null) || !is_string($row['properties'] ?? null)
            || !is_string($row['seen_at'] ?? null)
        ) {
            return null;
        }
        $seenAt = strtotime($row['seen_at'] . ' UTC');

        return $this->observations->create([
            'storeId' => $storeId,
            'kind' => $kind,
            'path' => $row['path'],
            'items' => $this->types->itemsAmong($this->itemsOf($row['properties'])),
            'seenAt' => $seenAt === false ? 0 : $seenAt,
        ]);
    }

    /**
     * The items a row keeps
     *
     * @param string $json
     * @return list<MicrodataItemInterface>
     */
    private function itemsOf(string $json): array
    {
        $kept = $this->json->unserialize($json);
        $items = [];
        foreach (is_array($kept) ? $kept : [] as $item) {
            $type = is_array($item) && is_string($item['type'] ?? null) ? $item['type'] : '';
            $properties = is_array($item) && is_array($item['properties'] ?? null) ? $item['properties'] : [];
            if ($type === '') {
                continue;
            }
            $items[] = $this->items->create([
                'type' => $type,
                'properties' => array_values(array_filter($properties, 'is_string')),
            ]);
        }

        return $items;
    }
}
