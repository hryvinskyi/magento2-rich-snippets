<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Test\Integration\Model\Emulation;

use Hryvinskyi\JsonTemplate\Api\TemplateEngineInterface;
use Hryvinskyi\RichSnippets\Api\FrontendEmulationInterface;
use Hryvinskyi\RichSnippets\Api\PageDataPrefetchInterface;
use Hryvinskyi\RichSnippets\Api\PageEntityFactoryInterface;
use Hryvinskyi\RichSnippets\Api\PageScopeFactoryInterface;
use Magento\Catalog\Test\Fixture\Product as ProductFixture;
use Magento\Framework\DataObject;
use Magento\Framework\Model\AbstractModel;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Magento\TestFramework\Fixture\AppArea;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Fixture\DataFixtureStorageManager;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * A preview evaluated in the admin, inside the frontend emulation, writes the storefront's addresses
 */
#[
    AppArea('adminhtml'),
    DbIsolation(true),
    DataFixture(ProductFixture::class, ['name' => 'Field satchel', 'url_key' => 'rs-field-satchel'], as: 'satchel'),
]
class FrontendEmulationTest extends TestCase
{
    private const TEMPLATE = '{"product": "{{product.url}}", "home": "{{store.url}}",'
        . ' "relative": "{{\'bags/field-satchel.html\' | url}}", "currency": "{{store.currency}}"}';

    /**
     * @var ObjectManagerInterface
     */
    private ObjectManagerInterface $objectManager;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        $this->objectManager = Bootstrap::getObjectManager();
        $this->get(PageDataPrefetchInterface::class)->reset();
    }

    public function testAnEmulatedPreviewWritesStorefrontAddresses(): void
    {
        $storeManager = $this->get(StoreManagerInterface::class);
        $store = $storeManager->getDefaultStoreView();
        self::assertNotNull($store);
        $storeId = (int) $store->getId();
        $frontend = $storeManager->getStore($storeId);
        self::assertInstanceOf(Store::class, $frontend);
        $base = rtrim((string) $frontend->getBaseUrl(UrlInterface::URL_TYPE_LINK), '/') . '/';
        $page = $this->get(PageEntityFactoryInterface::class)->create('product', $this->id(), $storeId);
        $current = $storeManager->getStore()->getCode();

        $output = $this->get(FrontendEmulationInterface::class)->run(
            $storeId,
            fn (): mixed => $this->engine()->evaluate(
                self::TEMPLATE,
                $this->get(PageScopeFactoryInterface::class)->create($page)
            )
        );

        self::assertSame(
            [
                'product' => $base . 'rs-field-satchel.html',
                'home' => $base,
                'relative' => $base . 'bags/field-satchel.html',
                'currency' => 'USD',
            ],
            $output
        );
        self::assertStringNotContainsString('/backend/', $base);
        self::assertSame($current, $storeManager->getStore()->getCode(), 'The store view before the run is back.');
    }

    /**
     * The fixture product's ID
     *
     * @return int
     */
    private function id(): int
    {
        $fixture = DataFixtureStorageManager::getStorage()->get('satchel');
        self::assertInstanceOf(DataObject::class, $fixture);

        return $this->entityId($fixture);
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
}
