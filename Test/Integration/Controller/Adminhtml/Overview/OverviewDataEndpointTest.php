<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Test\Integration\Controller\Adminhtml\Overview;

use Hryvinskyi\RichSnippets\Model\Admin\AdminResources;
use Hryvinskyi\RichSnippets\Model\Cache\Type\RichSnippetsCache;
use Hryvinskyi\RichSnippets\Model\Check\Storage\CheckTables;
use Hryvinskyi\RichSnippets\Model\ResourceModel\Snippet as SnippetResource;
use Hryvinskyi\RichSnippets\Test\Integration\Fixture\AsksAsTheSnippetsPage;
use Hryvinskyi\RichSnippets\Test\Integration\Fixture\StoresTestSnippets;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\App\ResourceConnection;
use Magento\Store\Model\StoreManagerInterface;
use Magento\TestFramework\Fixture\AppArea;
use Magento\TestFramework\Fixture\AppIsolation;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\TestCase\AbstractBackendController;

/**
 * The Overview's endpoint answers its page the way the page reads it, for every store view or one, refuses a store
 * view the shop does not have, and is closed to a role that may not see the module
 */
#[
    AppArea('adminhtml'),
    AppIsolation(true),
    DbIsolation(true),
]
class OverviewDataEndpointTest extends AbstractBackendController
{
    use AsksAsTheSnippetsPage;
    use StoresTestSnippets;

    private const DATA = 'backend/richsnippets/overview/data';
    private const NO_ACCESS = 403;

    /**
     * @var string
     */
    protected $resource = AdminResources::VIEW;

    /**
     * @var string
     */
    protected $uri = self::DATA;

    /**
     * @var string
     */
    protected $httpMethod = HttpRequest::METHOD_GET;

    /**
     * Start every test with no snippet and no run, whatever the database kept
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $resource = $this->testObjectManager()->get(ResourceConnection::class);
        self::assertInstanceOf(ResourceConnection::class, $resource);
        $resource->getConnection()->delete($resource->getTableName(CheckTables::RUN));
        $resource->getConnection()->delete($resource->getTableName(SnippetResource::TABLE));
        $this->cache()->clean();
    }

    /**
     * @return void
     */
    protected function tearDown(): void
    {
        $this->cache()->clean();
        parent::tearDown();
    }

    public function testAShopWithNoSnippetIsAnsweredAsNothingSetUp(): void
    {
        $answer = $this->read(self::DATA);

        self::assertSame('none', $answer['state'] ?? null);
        self::assertNull($this->at($answer, 'store'));
        self::assertSame(false, $this->at($answer, 'badge', 'on'));
        self::assertSame('other', $this->at($answer, 'kinds', 'rows', 4, 'kind'));
    }

    public function testALiveShopIsAnsweredForOneStoreView(): void
    {
        $this->storeSnippet('rs-endpoint-product', 'Product', [
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => '{{product.name}}',
        ], ['kinds' => ['product'], 'active' => true]);
        $storeId = $this->storeId();

        $answer = $this->read(self::DATA, ['store' => (string) $storeId]);

        self::assertSame(['live', $storeId, true, '1 snippet on'], [
            $answer['state'] ?? null,
            $this->at($answer, 'store'),
            $this->at($answer, 'badge', 'on'),
            $this->at($answer, 'badge', 'text'),
        ]);
        self::assertSame('Product', $this->at($answer, 'kinds', 'rows', 1, 'snippets', 0, 'name'));
        self::assertSame((string) $storeId, $this->at($answer, 'status', 'action', 'params', 'store'));
    }

    public function testASnippetLimitedToOneProductTypeSaysHowManyProductsOfTheTypeItReaches(): void
    {
        $this->storeSnippet('rs-endpoint-virtual', 'Virtual products', [
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => '{{product.name}}',
        ], ['kinds' => ['product'], 'active' => true, 'conditions' => [['product_type', '==', 'virtual']]]);

        $answer = $this->read(self::DATA, ['store' => (string) $this->storeId()]);

        self::assertSame('Virtual products', $this->at($answer, 'kinds', 'rows', 1, 'snippets', 0, 'name'));
        $sub = $this->at($answer, 'kinds', 'rows', 1, 'snippets', 0, 'sub');
        self::assertIsString($sub);
        self::assertMatchesRegularExpression('/^\d+ Virtual Product$/', $sub);
    }

    public function testWithoutTheRightToRunTestsALiveShopOffersNoPageTest(): void
    {
        $this->storeSnippet('rs-endpoint-product', 'Product', [
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => '{{product.name}}',
        ], ['kinds' => ['product'], 'active' => true]);
        $this->deny(AdminResources::TEST);

        $answer = $this->read(self::DATA, ['store' => (string) $this->storeId()]);

        self::assertSame('live', $answer['state'] ?? null);
        self::assertNull($this->at($answer, 'status', 'action'));
        $rows = $this->at($answer, 'kinds', 'rows');
        self::assertIsArray($rows);
        foreach ($rows as $row) {
            self::assertIsArray($row);
            self::assertNull($row['see'] ?? null);
        }
    }

    public function testAStoreViewTheShopDoesNotHaveIsRefused(): void
    {
        $answer = $this->read(self::DATA, ['store' => '999'], 400);

        self::assertSame('store', $answer['error'] ?? null);
    }

    public function testARoleThatMayNotSeeTheModuleIsRefusedTheOverview(): void
    {
        $this->deny(AdminResources::VIEW);
        $this->newExchange();
        $this->askAsTheBrowser(HttpRequest::METHOD_GET);

        $this->follow(self::DATA);

        self::assertSame(self::NO_ACCESS, $this->httpResponse()->getHttpResponseCode());
    }

    public function testARoleThatMaySeeTheModuleButNotChangeOrTestIsAnsweredTheOverview(): void
    {
        $this->deny(AdminResources::SNIPPETS);
        $this->deny(AdminResources::TEST);

        $answer = $this->read(self::DATA);

        self::assertSame('none', $answer['state'] ?? null);
    }

    /**
     * The default store view's ID
     *
     * @return int
     */
    private function storeId(): int
    {
        $storeManager = $this->testObjectManager()->get(StoreManagerInterface::class);
        self::assertInstanceOf(StoreManagerInterface::class, $storeManager);

        return (int) $storeManager->getDefaultStoreView()?->getId();
    }

    /**
     * The module's cache
     *
     * @return RichSnippetsCache
     */
    private function cache(): RichSnippetsCache
    {
        $cache = $this->testObjectManager()->get(RichSnippetsCache::class);
        self::assertInstanceOf(RichSnippetsCache::class, $cache);

        return $cache;
    }
}
