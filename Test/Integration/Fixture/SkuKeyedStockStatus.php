<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Test\Integration\Fixture;

use Magento\CatalogInventory\Model\ResourceModel\Stock\Status;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Magento\Store\Model\Website;

/**
 * The platform's stock status join as it reads for a website selling from a stock of its own: keyed by SKU instead
 * of the default stock's product ID, with its own status column, and saying a product can be sold only when the test
 * lists its SKU, whatever the default stock's index holds
 */
class SkuKeyedStockStatus extends Status
{
    /**
     * @var list<string> the SKUs the website's stock can sell
     */
    public array $salableSkus = [];

    /**
     * @inheritDoc
     */
    public function addStockStatusToSelect(Select $select, Website $website)
    {
        $connection = $this->getConnection();
        if (!$connection instanceof AdapterInterface) {
            throw new \LogicException('The stock status has no connection.');
        }
        $salable = $this->salableSkus === []
            ? '0'
            : $connection->quoteInto('website_stock.sku IN (?)', $this->salableSkus);
        $stock = $connection->select()->from(
            ['website_stock' => $this->getTable('catalog_product_entity')],
            ['sku', 'is_salable' => new \Zend_Db_Expr('(' . $salable . ')')]
        );
        $select->joinLeft(['stock_status' => $stock], 'e.sku = stock_status.sku', ['is_salable' => 'is_salable']);

        return $this;
    }
}
