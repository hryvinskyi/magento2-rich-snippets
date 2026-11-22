<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Page\Cms;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Store\Model\ScopeInterface;

/**
 * Reads the home and page-not-found CMS pages from the store view's web configuration
 *
 * A configured value is a page identifier (`home`), or an identifier and a page ID (`home|2`), in which case the ID
 * decides, as the storefront does. An identifier is looked up among the active pages of the store view, a page
 * assigned to the store view itself winning over one assigned to every store view.
 */
class ConfiguredStoreCmsPages implements StoreCmsPagesInterface
{
    private const HOME_PAGE_PATH = 'web/default/cms_home_page';
    private const NO_ROUTE_PAGE_PATH = 'web/default/cms_no_route';
    private const ID_DELIMITER = '|';
    private const PAGE_TABLE = 'cms_page';
    private const PAGE_STORE_TABLE = 'cms_page_store';

    /**
     * @param ScopeConfigInterface $config
     * @param ResourceConnection $resource
     */
    public function __construct(
        private readonly ScopeConfigInterface $config,
        private readonly ResourceConnection $resource
    ) {
    }

    /**
     * @inheritDoc
     */
    public function homePageId(int $storeId): ?int
    {
        return $this->configuredPageId(self::HOME_PAGE_PATH, $storeId);
    }

    /**
     * @inheritDoc
     */
    public function noRoutePageId(int $storeId): ?int
    {
        return $this->configuredPageId(self::NO_ROUTE_PAGE_PATH, $storeId);
    }

    /**
     * The ID of the active page of the store view a configuration path names
     *
     * @param string $path
     * @param int $storeId
     * @return int|null
     */
    private function configuredPageId(string $path, int $storeId): ?int
    {
        $value = $this->config->getValue($path, ScopeInterface::SCOPE_STORE, $storeId);
        if (!is_string($value) || trim($value) === '') {
            return null;
        }
        $delimiter = strrpos($value, self::ID_DELIMITER);
        $connection = $this->resource->getConnection();
        $select = $connection->select()
            ->from(['page' => $this->resource->getTableName(self::PAGE_TABLE)], ['page_id'])
            ->join(
                ['page_store' => $this->resource->getTableName(self::PAGE_STORE_TABLE)],
                'page_store.page_id = page.page_id',
                []
            )
            ->where('page.is_active = ?', 1)
            ->where('page_store.store_id IN (?)', [0, $storeId])
            ->order('page_store.store_id DESC')
            ->limit(1);
        if ($delimiter !== false) {
            $select->where('page.page_id = ?', (int) substr($value, $delimiter + 1));
        } else {
            $select->where('page.identifier = ?', trim($value));
        }
        $id = $connection->fetchOne($select);

        return is_numeric($id) ? (int) $id : null;
    }
}
