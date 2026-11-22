<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Test\Unit\Model\Data\Product;

use Hryvinskyi\RichSnippets\Model\Catalog\StockStatusJoin;
use Hryvinskyi\RichSnippets\Model\Data\Product\StockPart;
use Hryvinskyi\RichSnippets\Model\Data\QueryRows;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Magento\Framework\EntityManager\EntityMetadataInterface;
use Magento\Framework\EntityManager\MetadataPool;
use PHPUnit\Framework\TestCase;

/**
 * Stock status is written as the schema.org availability URL, read for all the products in one select that carries
 * the storefront's stock status join
 */
class StockPartTest extends TestCase
{
    public function testTheStockStatusIsTheSchemaOrgAvailabilityUrl(): void
    {
        $select = $this->createMock(Select::class);
        $select->method('from')->willReturnSelf();
        $select->method('where')->willReturnSelf();
        $stock = $this->createMock(StockStatusJoin::class);
        $stock->expects(self::once())->method('addStatus')->with($select, 'product.entity_id', 7, 'in_stock');
        $rows = $this->createMock(QueryRows::class);
        $rows->expects(self::once())->method('all')->with($select)->willReturn([
            ['entity_id' => '1', 'in_stock' => '1'],
            ['entity_id' => '2', 'in_stock' => '0'],
            ['entity_id' => '3', 'in_stock' => null],
        ]);

        $entries = $this->part($stock, $rows, $select)->load([1, 2, 3], 7, [], []);

        self::assertSame([
            1 => ['availability' => 'https://schema.org/InStock'],
            2 => ['availability' => 'https://schema.org/OutOfStock'],
            3 => ['availability' => 'https://schema.org/OutOfStock'],
        ], $entries);
    }

    public function testNoIdsReadNothing(): void
    {
        $stock = $this->createMock(StockStatusJoin::class);
        $stock->expects(self::never())->method('addStatus');
        $rows = $this->createMock(QueryRows::class);
        $rows->expects(self::never())->method('all');

        self::assertSame([], $this->part($stock, $rows, $this->createMock(Select::class))->load([], 7, [], []));
    }

    /**
     * @param StockStatusJoin $stock
     * @param QueryRows $rows
     * @param Select $select the select the connection starts
     * @return StockPart
     */
    private function part(StockStatusJoin $stock, QueryRows $rows, Select $select): StockPart
    {
        $connection = $this->createMock(AdapterInterface::class);
        $connection->method('select')->willReturn($select);
        $resource = $this->createMock(ResourceConnection::class);
        $resource->method('getConnection')->willReturn($connection);
        $metadata = $this->createMock(EntityMetadataInterface::class);
        $metadata->method('getEntityTable')->willReturn('catalog_product_entity');
        $pool = $this->createMock(MetadataPool::class);
        $pool->method('getMetadata')->willReturn($metadata);

        return new StockPart($stock, $rows, $resource, $pool);
    }
}
