<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Page\Search;

use Hryvinskyi\RichSnippets\Model\Catalog\EntityAddresses;
use Hryvinskyi\RichSnippets\Model\Data\QueryRows;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Helper as DbHelper;
use Magento\Framework\DB\Select;

/**
 * CMS pages, named and found by their title
 */
class CmsTitledPages implements PageSearchSourceInterface
{
    private const PAGE_TABLE = 'cms_page';
    private const URL_ENTITY_TYPE = 'cms-page';
    private const ROUTE = 'cms/page/view/page_id/%d';

    /**
     * @param ResourceConnection $resource
     * @param QueryRows $rows
     * @param DbHelper $dbHelper
     * @param EntityAddresses $addresses
     */
    public function __construct(
        private readonly ResourceConnection $resource,
        private readonly QueryRows $rows,
        private readonly DbHelper $dbHelper,
        private readonly EntityAddresses $addresses
    ) {
    }

    /**
     * @inheritDoc
     */
    public function names(Select $pages, string $query, int $storeId, int $limit): array
    {
        $select = $this->resource->getConnection()->select()
            ->from(['pages' => $pages], ['entity_id'])
            ->join(
                ['page' => $this->resource->getTableName(self::PAGE_TABLE)],
                'page.page_id = pages.entity_id',
                ['title' => 'page.title']
            )
            ->order('page.title ASC')
            ->order('pages.entity_id ASC')
            ->limit($limit);
        if ($query !== '') {
            $select->where('page.title LIKE ?', '%' . $this->dbHelper->escapeLikeValue($query) . '%');
        }
        $names = [];
        foreach ($this->rows->all($select) as $row) {
            $names[(int) $row['entity_id']] = (string) $row['title'];
        }

        return $names;
    }

    /**
     * @inheritDoc
     */
    public function urls(array $ids, int $storeId): array
    {
        return $this->addresses->urls(self::URL_ENTITY_TYPE, self::ROUTE, $ids, $storeId);
    }
}
