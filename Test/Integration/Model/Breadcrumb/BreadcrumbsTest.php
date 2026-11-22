<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Test\Integration\Model\Breadcrumb;

use Hryvinskyi\JsonTemplate\Api\TemplateEngineInterface;
use Hryvinskyi\RichSnippets\Api\PageDataPrefetchInterface;
use Hryvinskyi\RichSnippets\Api\PageEntityFactoryInterface;
use Hryvinskyi\RichSnippets\Api\PageScopeFactoryInterface;
use Magento\Catalog\Model\Session as CatalogSession;
use Magento\Catalog\Test\Fixture\Category as CategoryFixture;
use Magento\Catalog\Test\Fixture\Product as ProductFixture;
use Magento\Cms\Test\Fixture\Page as PageFixture;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\DataObject;
use Magento\Framework\Model\AbstractModel;
use Magento\Framework\ObjectManagerInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\Fixture\DataFixtureStorageManager;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * Breadcrumb levels depend on the catalog and the page's address only, never on what the visitor browsed before
 *
 * The product is in "Bags" and in "Leather" below it, so its default trail runs through the deeper "Leather".
 */
#[
    DbIsolation(true),
    DataFixture(CategoryFixture::class, ['name' => 'Bags RS'], as: 'bags'),
    DataFixture(CategoryFixture::class, ['name' => 'Leather RS', 'parent_id' => '$bags.id$'], as: 'leather'),
    DataFixture(CategoryFixture::class, ['name' => 'Elsewhere RS'], as: 'elsewhere'),
    DataFixture(
        ProductFixture::class,
        ['name' => 'Field satchel', 'category_ids' => ['$bags.id$', '$leather.id$']],
        as: 'satchel'
    ),
    DataFixture(PageFixture::class, ['identifier' => 'rs-shipping', 'title' => 'Shipping & returns'], as: 'shipping'),
]
class BreadcrumbsTest extends TestCase
{
    private const TEMPLATE = '{"@type": "BreadcrumbList", "itemListElement": {"@each": "breadcrumbs.items",'
        . ' "@type": "ListItem", "position": "{{crumb.position}}", "name": "{{crumb.name}}",'
        . ' "item": "{{crumb.item}}"}}';

    /**
     * @var ObjectManagerInterface
     */
    private ObjectManagerInterface $objectManager;

    /**
     * @var int
     */
    private int $storeId = 0;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        $this->objectManager = Bootstrap::getObjectManager();
        $this->storeId = (int) $this->get(StoreManagerInterface::class)->getDefaultStoreView()?->getId();
        $this->get(PageDataPrefetchInterface::class)->reset();
    }

    /**
     * @return void
     */
    protected function tearDown(): void
    {
        $request = $this->get(HttpRequest::class);
        $request->setParams([]);
        $request->setRouteName('');
        $request->setControllerName('');
        $request->setActionName('');
        $this->get(CatalogSession::class)->__call('unsLastVisitedCategoryId', []);
        $this->get(PageDataPrefetchInterface::class)->reset();
    }

    public function testAProductPagesTrailRunsThroughItsDeepestCategoryWhateverTheVisitorBrowsed(): void
    {
        $without = $this->trail('product', $this->id('satchel'));
        $this->get(PageDataPrefetchInterface::class)->reset();
        $session = $this->get(CatalogSession::class);
        $session->__call('setLastVisitedCategoryId', [$this->id('elsewhere')]);
        self::assertSame($this->id('elsewhere'), $session->__call('getLastVisitedCategoryId', []));
        $with = $this->trail('product', $this->id('satchel'));

        self::assertSame(['Home', 'Bags RS', 'Leather RS', 'Field satchel'], $this->names($without));
        self::assertSame($without, $with, 'The last visited category changes nothing.');
        self::assertSame([1, 2, 3, 4], array_column($without, 'position'));
        foreach ($without as $level) {
            self::assertIsString($level['item']);
            self::assertStringStartsWith('http', $level['item']);
        }
    }

    public function testAProductAddressThroughACategoryOfTheProductRunsTheTrailThroughIt(): void
    {
        $this->openProductPage($this->id('satchel'), $this->id('bags'));

        self::assertSame(
            ['Home', 'Bags RS', 'Field satchel'],
            $this->names($this->trail('product', $this->id('satchel')))
        );
    }

    public function testAnAddressThroughACategoryTheProductIsNotInIsIgnored(): void
    {
        $this->openProductPage($this->id('satchel'), $this->id('elsewhere'));

        self::assertSame(
            ['Home', 'Bags RS', 'Leather RS', 'Field satchel'],
            $this->names($this->trail('product', $this->id('satchel')))
        );
    }

    public function testCategoryAndCmsPagesHaveTheirTrailsAndTheHomePageHasNone(): void
    {
        self::assertSame(
            ['Home', 'Bags RS', 'Leather RS'],
            $this->names($this->trail('category', $this->id('leather')))
        );
        self::assertSame(['Home', 'Shipping & returns'], $this->names($this->trail('cms', $this->id('shipping'))));
        self::assertSame([], $this->trail('home', $this->id('shipping')));
    }

    /**
     * Make the current request the product's page, reached through a category's address
     *
     * @param int $productId
     * @param int $categoryId
     * @return void
     */
    private function openProductPage(int $productId, int $categoryId): void
    {
        $request = $this->get(HttpRequest::class);
        $request->setRouteName('catalog');
        $request->setControllerName('product');
        $request->setActionName('view');
        $request->setParams(['id' => (string) $productId, 'category' => (string) $categoryId]);
    }

    /**
     * The breadcrumb levels of a page
     *
     * @param string $kind
     * @param int $entityId
     * @return list<array<string, mixed>>
     */
    private function trail(string $kind, int $entityId): array
    {
        $page = $this->get(PageEntityFactoryInterface::class)->create($kind, $entityId, $this->storeId);
        $output = $this->engine()->evaluate(
            self::TEMPLATE,
            $this->get(PageScopeFactoryInterface::class)->create($page)
        );
        if ($output === null) {
            return [];
        }
        self::assertIsArray($output);
        self::assertIsArray($output['itemListElement']);
        $levels = [];
        foreach ($output['itemListElement'] as $level) {
            self::assertIsArray($level);
            $levels[] = ['position' => $level['position'], 'name' => $level['name'], 'item' => $level['item']];
        }

        return $levels;
    }

    /**
     * The names of the levels
     *
     * @param list<array<string, mixed>> $trail
     * @return list<mixed>
     */
    private function names(array $trail): array
    {
        return array_column($trail, 'name');
    }

    /**
     * A fixture's ID
     *
     * @param string $name
     * @return int
     */
    private function id(string $name): int
    {
        $fixture = DataFixtureStorageManager::getStorage()->get($name);
        self::assertInstanceOf(DataObject::class, $fixture);

        return $this->entityId($fixture);
    }

    /**
     * The snippets' template engine
     *
     * @return TemplateEngineInterface
     */
    private function engine(): TemplateEngineInterface
    {
        $engine = $this->objectManager->get('HryvinskyiRichSnippetsTemplateEngine');
        self::assertInstanceOf(TemplateEngineInterface::class, $engine);

        return $engine;
    }

    /**
     * A service from the container
     *
     * @template T of object
     * @param class-string<T> $type
     * @return T
     */
    private function get(string $type): object
    {
        $service = $this->objectManager->get($type);
        self::assertInstanceOf($type, $service);

        return $service;
    }

    /**
     * The ID of a fixture's entity
     *
     * @param DataObject $fixture
     * @return int
     */
    private function entityId(DataObject $fixture): int
    {
        self::assertInstanceOf(AbstractModel::class, $fixture);
        $id = $fixture->getId();
        self::assertIsNumeric($id);

        return (int) $id;
    }
}
