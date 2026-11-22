<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Page\Existing;

use Hryvinskyi\RichSnippets\Api\ExistingPagesInterface;
use Hryvinskyi\RichSnippets\Model\Page\Cms\StoreCmsPagesInterface;
use Hryvinskyi\RichSnippets\Model\Page\ExistingPageSourceInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Select;

/**
 * The CMS pages of a store view: its active CMS pages, or those of every store view, other than its home page and
 * its page-not-found page
 *
 * The home page is a page of the home kind, and the page-not-found page is shown for addresses of no other kind, so
 * neither is counted twice.
 */
class CmsPages implements ExistingPageSourceInterface
{
    private const PAGE_TABLE = 'cms_page';
    private const PAGE_STORE_TABLE = 'cms_page_store';

    /**
     * @param ResourceConnection $resource
     * @param StoreCmsPagesInterface $cmsPages
     */
    public function __construct(
        private readonly ResourceConnection $resource,
        private readonly StoreCmsPagesInterface $cmsPages
    ) {
    }

    /**
     * @inheritDoc
     */
    public function select(int $storeId): Select
    {
        $connection = $this->resource->getConnection();
        $inStore = $connection->select()
            ->from($this->resource->getTableName(self::PAGE_STORE_TABLE), ['page_id'])
            ->where('store_id IN (?)', [0, $storeId]);
        $select = $connection->select()
            ->from(
                ['page' => $this->resource->getTableName(self::PAGE_TABLE)],
                [ExistingPagesInterface::ENTITY_ID => 'page.page_id']
            )
            ->where('page.is_active = ?', 1)
            ->where('page.page_id IN ?', $inStore);
        $roles = array_values(array_filter([
            $this->cmsPages->homePageId($storeId),
            $this->cmsPages->noRoutePageId($storeId),
        ], static fn (?int $id): bool => $id !== null));
        if ($roles !== []) {
            $select->where('page.page_id NOT IN (?)', $roles);
        }

        return $select;
    }
}
