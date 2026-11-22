<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Data\Cms;

use Hryvinskyi\RichSnippets\Model\Data\QueryRows;
use Hryvinskyi\RichSnippets\Api\EntityDataPartInterface;
use Hryvinskyi\RichSnippets\Model\Catalog\CanonicalUrls;
use Hryvinskyi\RichSnippets\Model\Page\Cms\StoreCmsPagesInterface;
use Hryvinskyi\RichSnippets\Model\Store\StoreAddresses;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;

/**
 * Each CMS page's own values in the store view: `title`, `identifier`, `heading`, `meta_title`, `meta_description`,
 * `created_at` and `updated_at` (ISO 8601 with the store view's offset) and `url`, the store view's base address for
 * its home page and the page's own address otherwise
 */
class PagePart implements EntityDataPartInterface
{
    private const PAGE_TABLE = 'cms_page';
    private const PAGE_STORE_TABLE = 'cms_page_store';
    private const REWRITE_TYPE = 'cms-page';

    /**
     * @param QueryRows $rows
     * @param ResourceConnection $resource
     * @param CanonicalUrls $canonical
     * @param StoreAddresses $addresses
     * @param StoreCmsPagesInterface $storePages
     * @param TimezoneInterface $timezone
     */
    public function __construct(
        private readonly QueryRows $rows,
        private readonly ResourceConnection $resource,
        private readonly CanonicalUrls $canonical,
        private readonly StoreAddresses $addresses,
        private readonly StoreCmsPagesInterface $storePages,
        private readonly TimezoneInterface $timezone
    ) {
    }

    /**
     * @inheritDoc
     */
    public function load(array $ids, int $storeId, array $variablePaths, array $collectionLimits): array
    {
        if ($ids === []) {
            return [];
        }
        $connection = $this->resource->getConnection();
        $select = $connection->select()
            ->distinct()
            ->from(['page' => $this->resource->getTableName(self::PAGE_TABLE)], [
                'page_id',
                'title',
                'identifier',
                'content_heading',
                'meta_title',
                'meta_description',
                'creation_time',
                'update_time',
            ])
            ->join(
                ['page_store' => $this->resource->getTableName(self::PAGE_STORE_TABLE)],
                'page_store.page_id = page.page_id',
                []
            )
            ->where('page.page_id IN (?)', $ids)
            ->where('page_store.store_id IN (?)', array_values(array_unique([0, $storeId])));
        $rows = $this->rows->all($select);
        $paths = $this->canonical->paths(
            self::REWRITE_TYPE,
            array_map(static fn (array $row): int => (int) $row['page_id'], $rows),
            $storeId
        );
        $home = $this->storePages->homePageId($storeId);
        $entries = [];
        foreach ($rows as $row) {
            $id = (int) $row['page_id'];
            $entries[$id] = [
                'title' => $this->text($row['title']),
                'identifier' => $this->text($row['identifier']),
                'heading' => $this->text($row['content_heading']),
                'meta_title' => $this->text($row['meta_title']),
                'meta_description' => $this->text($row['meta_description']),
                'created_at' => $this->moment($row['creation_time'], $storeId),
                'updated_at' => $this->moment($row['update_time'], $storeId),
                'url' => $this->addresses->page(
                    $id === $home ? '' : ($paths[$id] ?? (string) $row['identifier']),
                    $storeId
                ),
            ];
        }

        return $entries;
    }

    /**
     * A stored text; null when empty
     *
     * @param mixed $value
     * @return string|null
     */
    private function text(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * A stored UTC moment as ISO 8601 in the store view's time zone; null when none is stored
     *
     * @param mixed $value
     * @param int $storeId
     * @return string|null
     */
    private function moment(mixed $value, int $storeId): ?string
    {
        return is_string($value) && $value !== ''
            ? $this->timezone->scopeDate($storeId, $value, true)->format(\DateTimeInterface::ATOM)
            : null;
    }
}
