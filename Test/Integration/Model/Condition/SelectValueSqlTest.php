<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Test\Integration\Model\Condition;

use Hryvinskyi\RichSnippets\Api\ConditionValueSqlInterface;
use Hryvinskyi\RichSnippets\Model\Condition\Sql\ConditionValueSqlFactoryInterface;
use Magento\Catalog\Test\Fixture\Product as ProductFixture;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Model\AbstractModel;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Fixture\DataFixtureStorageManager;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * A condition on a page's values runs in the database as written: a question mark inside a quoted value stays a
 * question mark, with and without a WHERE clause in the value select
 */
#[
    DbIsolation(true),
    DataFixture(ProductFixture::class, ['sku' => 'rs-value?sql', 'name' => 'Tote? RS'], as: 'product'),
]
class SelectValueSqlTest extends TestCase
{
    public function testAQuestionMarkInAValueIsMatchedAsWritten(): void
    {
        $sql = $this->valueSql(true);

        self::assertSame('1', $this->ask($sql->exists('rs_product.sku = ' . $sql->quote('rs-value?sql'))));
        self::assertSame('0', $this->ask($sql->exists('rs_product.sku = ' . $sql->quote('rs-value?other'))));
        self::assertSame('1', $this->ask($sql->exists('rs_product.sku LIKE ' . $sql->quote('%value?%'))));
    }

    public function testAValueSelectWithoutAWhereClauseTakesTheConditionAsItsWhere(): void
    {
        $sql = $this->valueSql(false);

        self::assertSame('1', $this->ask($sql->exists('rs_product.sku = ' . $sql->quote('rs-value?sql'))));
    }

    /**
     * The SKUs of products as value SQL, correlated to the fixture product or to none
     *
     * @param bool $correlated
     * @return ConditionValueSqlInterface
     */
    private function valueSql(bool $correlated): ConditionValueSqlInterface
    {
        $resource = $this->get(ResourceConnection::class);
        $select = $resource->getConnection()->select()->from(
            ['rs_product' => $resource->getTableName('catalog_product_entity')],
            ['rs_product.entity_id']
        );
        if ($correlated) {
            $select->where('rs_product.entity_id = ' . $this->productId());
        }

        return $this->get(ConditionValueSqlFactoryInterface::class)->create($select, 'rs_product.sku');
    }

    /**
     * Run an EXISTS test
     *
     * @param string $exists
     * @return string `1` or `0`
     */
    private function ask(string $exists): string
    {
        return $this->get(ResourceConnection::class)->getConnection()->fetchOne('SELECT ' . $exists);
    }

    /**
     * @return int
     */
    private function productId(): int
    {
        $product = DataFixtureStorageManager::getStorage()->get('product');
        self::assertInstanceOf(AbstractModel::class, $product);
        $id = $product->getId();
        self::assertIsNumeric($id);

        return (int) $id;
    }

    /**
     * A service from the container, of the type asked for
     *
     * @template T of object
     * @param class-string<T> $type
     * @return T
     */
    private function get(string $type): object
    {
        $service = Bootstrap::getObjectManager()->get($type);
        self::assertInstanceOf($type, $service);

        return $service;
    }
}
