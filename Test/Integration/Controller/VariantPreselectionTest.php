<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Test\Integration\Controller;

use Magento\Catalog\Test\Fixture\Product as ProductFixture;
use Magento\ConfigurableProduct\Test\Fixture\Attribute as ConfigurableAttributeFixture;
use Magento\ConfigurableProduct\Test\Fixture\Product as ConfigurableProductFixture;
use Magento\Framework\App\Response\Http as HttpResponse;
use Magento\Framework\DataObject;
use Magento\Framework\Model\AbstractModel;
use Magento\TestFramework\Fixture\AppArea;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Fixture\DataFixtureStorageManager;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\TestCase\AbstractController;

/**
 * A configurable product's page opened at a variant's address opens with that variant chosen in its options
 *
 * The large child is out of stock, so its address preselects nothing.
 */
#[
    AppArea('frontend'),
    DbIsolation(true),
    DataFixture(ConfigurableAttributeFixture::class, as: 'size'),
    DataFixture(ProductFixture::class, ['sku' => 'rs-weekender-s', 'price' => 310], as: 'small'),
    DataFixture(
        ProductFixture::class,
        [
            'sku' => 'rs-weekender-l',
            'price' => 290,
            'extension_attributes' => ['stock_item' => ['qty' => 0, 'is_in_stock' => false]],
        ],
        as: 'large'
    ),
    DataFixture(
        ConfigurableProductFixture::class,
        ['sku' => 'rs-weekender', '_options' => ['$size$'], '_links' => ['$small$', '$large$']],
        as: 'weekender'
    ),
]
class VariantPreselectionTest extends AbstractController
{
    public function testTheAddressOfAVariantThatCanBeBoughtPreselectsIt(): void
    {
        $body = $this->page([$this->code() => $this->option('option_1')]);

        self::assertStringContainsString(
            '"defaultValues":{"' . $this->attributeId() . '":"' . $this->option('option_1') . '"}',
            $body
        );
    }

    public function testThePageWithoutAVariantsAddressPreselectsNothing(): void
    {
        self::assertStringNotContainsString('"defaultValues"', $this->page([]));
    }

    public function testTheAddressOfAVariantOutOfStockPreselectsNothing(): void
    {
        self::assertStringNotContainsString(
            '"defaultValues"',
            $this->page([$this->code() => $this->option('option_2')])
        );
    }

    public function testAnUnknownOptionPreselectsNothing(): void
    {
        self::assertStringNotContainsString('"defaultValues"', $this->page([$this->code() => '999999']));
    }

    /**
     * The configurable product's page with query parameters
     *
     * @param array<string, string> $params
     * @return string
     */
    private function page(array $params): string
    {
        $this->getRequest()->setParams($params);
        $this->dispatch('catalog/product/view/id/' . $this->entityId($this->fixture('weekender')));
        $response = $this->getResponse();
        self::assertInstanceOf(HttpResponse::class, $response);
        $body = $response->getBody();
        self::assertStringContainsString('rs-weekender', $body, 'The product page was rendered.');

        return $body;
    }

    /**
     * The configurable attribute's code
     *
     * @return string
     */
    private function code(): string
    {
        $code = $this->fixture('size')->getData('attribute_code');
        self::assertIsString($code);

        return $code;
    }

    /**
     * The configurable attribute's ID
     *
     * @return int
     */
    private function attributeId(): int
    {
        $id = $this->fixture('size')->getData('attribute_id');
        self::assertIsNumeric($id);

        return (int) $id;
    }

    /**
     * An option ID of the configurable attribute, by its label
     *
     * @param string $label
     * @return string
     */
    private function option(string $label): string
    {
        $option = $this->fixture('size')->getData($label);
        self::assertIsNumeric($option);

        return (string) (int) $option;
    }

    /**
     * A fixture
     *
     * @param string $name
     * @return DataObject
     */
    private function fixture(string $name): DataObject
    {
        $fixture = DataFixtureStorageManager::getStorage()->get($name);
        self::assertInstanceOf(DataObject::class, $fixture);

        return $fixture;
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
