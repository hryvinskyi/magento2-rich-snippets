<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Page\Search;

use Hryvinskyi\RichSnippets\Model\Catalog\EavStoreValueJoin;
use Hryvinskyi\RichSnippets\Model\Catalog\EntityAddresses;
use Hryvinskyi\RichSnippets\Model\Data\QueryRows;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Helper as DbHelper;
use Magento\Framework\DB\Select;

/**
 * Pages of a catalog entity named by its `name` attribute in the store view (products, categories), found by name or
 * by a code column of the entity table (a product's SKU)
 *
 * The text is matched anywhere, as typed: the catalog's case-insensitive collation decides the case, and the
 * characters `%` and `_` stand for themselves.
 */
class EavNamedPages implements PageSearchSourceInterface
{
    private const NAME = 'name';

    /**
     * @param ResourceConnection $resource
     * @param QueryRows $rows
     * @param DbHelper $dbHelper
     * @param EavStoreValueJoin $attributes the entity's attributes
     * @param EntityAddresses $addresses
     * @param string $urlEntityType the URL rewrite entity type (`product`, `category`)
     * @param string $route the platform's route to an entity without a rewrite, `%d` standing for its ID
     * @param string $entityTable the entity table holding the code column; empty when the entity has none
     * @param string $codeColumn a column of the entity table also searched (`sku`); empty for none
     */
    public function __construct(
        private readonly ResourceConnection $resource,
        private readonly QueryRows $rows,
        private readonly DbHelper $dbHelper,
        private readonly EavStoreValueJoin $attributes,
        private readonly EntityAddresses $addresses,
        private readonly string $urlEntityType,
        private readonly string $route,
        private readonly string $entityTable = '',
        private readonly string $codeColumn = ''
    ) {
    }

    /**
     * @inheritDoc
     */
    public function names(Select $pages, string $query, int $storeId, int $limit): array
    {
        $select = $this->resource->getConnection()->select()->from(['pages' => $pages], ['entity_id']);
        $name = $this->attributes->join($select, 'pages.entity_id', self::NAME, $storeId, self::NAME);
        $select->columns([self::NAME => $name])->order($name . ' ASC')->order('pages.entity_id ASC')->limit($limit);
        if ($query !== '') {
            $connection = $this->resource->getConnection();
            $like = '%' . $this->dbHelper->escapeLikeValue($query) . '%';
            $matches = [$connection->quoteInto($name . ' LIKE ?', $like)];
            if ($this->entityTable !== '' && $this->codeColumn !== '') {
                $select->join(
                    ['entity' => $this->resource->getTableName($this->entityTable)],
                    'entity.entity_id = pages.entity_id',
                    []
                );
                $matches[] = $connection->quoteInto(
                    $connection->quoteIdentifier('entity.' . $this->codeColumn) . ' LIKE ?',
                    $like
                );
            }
            // Quoted already: passed as a whole condition, so a `?` the text holds is never taken for a placeholder
            $select->where(implode(' OR ', $matches), null, Select::TYPE_CONDITION);
        }
        $names = [];
        foreach ($this->rows->all($select) as $row) {
            $names[(int) $row['entity_id']] = (string) $row[self::NAME];
        }

        return $names;
    }

    /**
     * @inheritDoc
     */
    public function urls(array $ids, int $storeId): array
    {
        return $this->addresses->urls($this->urlEntityType, $this->route, $ids, $storeId);
    }
}
