<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Catalog;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\CatalogInventory\Model\ResourceModel\Stock\Status;
use Magento\Framework\DB\Select;
use Magento\Framework\EntityManager\MetadataPool;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Store\Model\Website;

/**
 * Joins to a select of products the stock status the storefront reads for a store view's website
 *
 * The status comes from the platform's own join (Status::addStockStatusToSelect), the one an inventory module adapts
 * when a website sells from a stock other than the default one, so a snippet reads the stock the storefront filters
 * on rather than a copy of one way to read it. That join needs the product table under the alias `e` and adds its
 * table under `stock_status` with a column `is_salable`, whatever that column is read from; so the product table is
 * joined under `e` on the caller's product column, the column the platform's join added is taken back out of the
 * select, and its expression is used where the caller asks for it. A select given here must not use either alias
 * already. A product the stock does not hold reads as out of stock.
 */
class StockStatusJoin
{
    private const PRODUCT_ALIAS = 'e';
    private const SALABLE_COLUMN = 'is_salable';
    private const IN_STOCK = 1;

    /**
     * @param MetadataPool $metadataPool
     * @param StoreManagerInterface $storeManager
     * @param Status $statuses
     */
    public function __construct(
        private readonly MetadataPool $metadataPool,
        private readonly StoreManagerInterface $storeManager,
        private readonly Status $statuses
    ) {
    }

    /**
     * Add each product's stock status as a column, 1 for in stock, 0 or null otherwise
     *
     * @param Select $select
     * @param string $productColumn the qualified column holding the product ID (`child.entity_id`)
     * @param int $storeId the store view whose website's stock is read
     * @param string $column the name of the status column added
     * @return void
     */
    public function addStatus(Select $select, string $productColumn, int $storeId, string $column): void
    {
        $select->columns([$column => $this->joinStatus($select, $productColumn, $storeId)]);
    }

    /**
     * Keep only the products in stock
     *
     * @param Select $select
     * @param string $productColumn the qualified column holding the product ID (`listing.product_id`)
     * @param int $storeId the store view whose website's stock is read
     * @return void
     */
    public function limitToInStock(Select $select, string $productColumn, int $storeId): void
    {
        $select->where($this->joinStatus($select, $productColumn, $storeId) . ' = ?', self::IN_STOCK);
    }

    /**
     * Join the product table and the platform's stock status, and give back the status column as the select reads it
     *
     * @param Select $select
     * @param string $productColumn
     * @param int $storeId
     * @return string|\Zend_Db_Expr the qualified column (`stock_status.stock_status`), or the expression the join
     *     reads the status with
     * @throws \UnexpectedValueException when the platform's join adds no status column
     */
    private function joinStatus(Select $select, string $productColumn, int $storeId): string|\Zend_Db_Expr
    {
        $select->join(
            [self::PRODUCT_ALIAS => $this->metadataPool->getMetadata(ProductInterface::class)->getEntityTable()],
            self::PRODUCT_ALIAS . '.entity_id = ' . $productColumn,
            []
        );
        $columns = $this->columnsOf($select);
        $this->statuses->addStockStatusToSelect($select, $this->website($storeId));
        $added = array_slice($this->columnsOf($select), count($columns));
        $select->setPart(Select::COLUMNS, $columns);
        foreach ($added as $entry) {
            if (!is_array($entry) || ($entry[2] ?? null) !== self::SALABLE_COLUMN) {
                continue;
            }
            $column = $entry[1] ?? null;
            $correlation = $entry[0] ?? null;
            if ($column instanceof \Zend_Db_Expr) {
                return $column;
            }
            if (is_string($column) && is_string($correlation) && $correlation !== '') {
                return $correlation . '.' . $column;
            }
        }

        throw new \UnexpectedValueException('The platform\'s stock status join added no salable column that reads.');
    }

    /**
     * The columns a select lists, each as `[table alias, column or expression, alias]`
     *
     * @param Select $select
     * @return array<mixed>
     */
    private function columnsOf(Select $select): array
    {
        $columns = $select->getPart(Select::COLUMNS);

        return is_array($columns) ? $columns : [];
    }

    /**
     * The website of a store view, as the platform's join takes it
     *
     * @param int $storeId
     * @return Website
     * @throws \UnexpectedValueException when the store manager answers with another kind of website
     */
    private function website(int $storeId): Website
    {
        $website = $this->storeManager->getWebsite($this->storeManager->getStore($storeId)->getWebsiteId());
        if (!$website instanceof Website) {
            throw new \UnexpectedValueException('The store view\'s website is not one the stock status join reads.');
        }

        return $website;
    }
}
