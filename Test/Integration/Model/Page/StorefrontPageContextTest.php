<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Test\Integration\Model\Page;

use Hryvinskyi\RichSnippets\Api\Data\PageEntityInterface;
use Hryvinskyi\RichSnippets\Api\PageCacheTagsInterface;
use Hryvinskyi\RichSnippets\Api\PageContextInterface;
use Magento\Catalog\Test\Fixture\Category as CategoryFixture;
use Magento\Catalog\Test\Fixture\Product as ProductFixture;
use Magento\Cms\Test\Fixture\Page as PageFixture;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Model\AbstractModel;
use Magento\Store\Model\StoreManagerInterface;
use Magento\TestFramework\Fixture\AppArea;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Fixture\DataFixtureStorageManager;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\TestCase\AbstractController;

/**
 * The page a real storefront request renders: its kind from the full action name, its entity from the request
 *
 * Each test dispatches one address through the storefront's router and controllers, then reads the page context the
 * way the snippet output does while that page renders.
 */
#[
    AppArea('frontend'),
    DbIsolation(true),
    DataFixture(ProductFixture::class, as: 'product'),
    DataFixture(CategoryFixture::class, as: 'category'),
    DataFixture(PageFixture::class, ['identifier' => 'rs-test-faq'], as: 'page'),
]
class StorefrontPageContextTest extends AbstractController
{
    public function testAProductPageShowsTheProductOfTheAddress(): void
    {
        $this->dispatch('/catalog/product/view/id/' . $this->fixtureId('product'));

        $this->assertPage('product', $this->fixtureId('product'), $this->context()->current());
    }

    public function testACategoryPageShowsTheCategoryOfTheAddress(): void
    {
        $this->dispatch('/catalog/category/view/id/' . $this->fixtureId('category'));

        $this->assertPage('category', $this->fixtureId('category'), $this->context()->current());
    }

    public function testACmsPageShowsThePageTheRouterFound(): void
    {
        $this->dispatch('/rs-test-faq');

        $this->assertPage('cms', $this->fixtureId('page'), $this->context()->current());
    }

    public function testTheHomePageShowsTheConfiguredHomeCmsPageAndIsNoCmsPage(): void
    {
        $this->dispatch('/');

        $this->assertPage('home', $this->homePageId(), $this->context()->current());
    }

    public function testThePageNotFoundPageIsAnotherPage(): void
    {
        $this->dispatch('/rs-test-no-such-address');

        $this->assertPage('other', null, $this->context()->current());
    }

    public function testAPageOfNoListedKindIsAnotherPage(): void
    {
        $this->dispatch('/review/product/list/id/' . $this->fixtureId('product'));

        $this->assertPage('other', null, $this->context()->current());
    }

    public function testAPageWithNoSnippetCarriesTheTagsOfItsKindAndStoreView(): void
    {
        $this->dispatch('/catalog/category/view/id/' . $this->fixtureId('category'));
        $storeId = $this->storeId();

        self::assertSame(
            ['rich_snippets_kind_category_' . $storeId, 'rich_snippets_store_' . $storeId],
            $this->service(PageCacheTagsInterface::class)->forPage($this->context()->current())
        );
    }

    /**
     * Assert a page's kind, entity and store view
     *
     * @param string $kind
     * @param int|null $entityId
     * @param PageEntityInterface $page
     * @return void
     */
    private function assertPage(string $kind, ?int $entityId, PageEntityInterface $page): void
    {
        self::assertSame(
            [$kind, $entityId, $this->storeId()],
            [$page->getKind(), $page->getEntityId(), $page->getStoreId()]
        );
    }

    /**
     * The ID of the CMS page the default store view shows as its home page
     *
     * @return int
     */
    private function homePageId(): int
    {
        $resource = $this->service(ResourceConnection::class);
        $connection = $resource->getConnection();
        $id = $connection->fetchCol(
            $connection->select()
                ->from($resource->getTableName('cms_page'), ['page_id'])
                ->where('identifier = ?', 'home')
        )[0] ?? null;
        self::assertIsNumeric($id, 'The installation has a CMS page "home".');

        return (int) $id;
    }

    /**
     * @return int
     */
    private function storeId(): int
    {
        return (int) $this->service(StoreManagerInterface::class)->getStore()->getId();
    }

    /**
     * The ID of a fixture entity
     *
     * @param string $name
     * @return int
     */
    private function fixtureId(string $name): int
    {
        $fixture = DataFixtureStorageManager::getStorage()->get($name);
        self::assertInstanceOf(AbstractModel::class, $fixture);
        $id = $fixture->getId();
        self::assertIsNumeric($id);

        return (int) $id;
    }

    /**
     * @return PageContextInterface
     */
    private function context(): PageContextInterface
    {
        return $this->service(PageContextInterface::class);
    }

    /**
     * A service from the container, of the type asked for
     *
     * @template T of object
     * @param class-string<T> $type
     * @return T
     */
    private function service(string $type): object
    {
        $service = $this->_objectManager->get($type);
        self::assertInstanceOf($type, $service);

        return $service;
    }
}
