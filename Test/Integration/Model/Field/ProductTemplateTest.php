<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Test\Integration\Model\Field;

use Hryvinskyi\JsonTemplate\Api\TemplateEngineInterface;
use Hryvinskyi\RichSnippets\Api\Data\FieldInterface;
use Hryvinskyi\RichSnippets\Api\FieldCatalogInterface;
use Hryvinskyi\RichSnippets\Api\FrontendEmulationInterface;
use Hryvinskyi\RichSnippets\Api\PageDataPrefetchInterface;
use Hryvinskyi\RichSnippets\Api\PageEntityFactoryInterface;
use Hryvinskyi\RichSnippets\Api\PageScopeFactoryInterface;
use Hryvinskyi\RichSnippets\Model\Engine\SnippetIncludeResolver;
use Hryvinskyi\RichSnippets\Model\ResourceModel\Snippet as SnippetResource;
use Hryvinskyi\RichSnippets\Model\Snippet\SnippetFactoryInterface;
use Hryvinskyi\RichSnippets\Model\Snippet\SnippetRootRepositoryInterface;
use Hryvinskyi\RichSnippets\Model\Snippet\TemplateTypeInterface;
use Magento\Catalog\Test\Fixture\Attribute as AttributeFixture;
use Magento\Catalog\Test\Fixture\Product as ProductFixture;
use Magento\ConfigurableProduct\Test\Fixture\Attribute as ConfigurableAttributeFixture;
use Magento\ConfigurableProduct\Test\Fixture\Product as ConfigurableProductFixture;
use Magento\Customer\Model\Context as CustomerContext;
use Magento\Customer\Test\Fixture\CustomerGroup as CustomerGroupFixture;
use Magento\Framework\App\Http\Context as HttpContext;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Component\ComponentRegistrar;
use Magento\Framework\DataObject;
use Magento\Framework\Model\AbstractModel;
use Magento\Framework\ObjectManagerInterface;
use Magento\Review\Model\Rating;
use Magento\Review\Model\RatingFactory;
use Magento\Review\Model\Review;
use Magento\Review\Test\Fixture\Review as ReviewFixture;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Fixture\DataFixtureStorageManager;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * The design's Product template, evaluated for real products of the catalog, gives the design's shapes
 *
 * The products stand for the design's samples: a simple product with an EAN and a rated review, one with neither,
 * one on sale until a fixed day, and a configurable product whose cheapest child sets its price. A customer group
 * with its own price shows that the price follows the visitor's group from the page cache's context.
 *
 * The price index is filled once a product's save commits, so the test runs outside an isolating transaction; the
 * fixtures remove themselves and the test removes the building block it saves.
 */
#[
    DbIsolation(false),
    DataFixture(AttributeFixture::class, ['attribute_code' => 'ean', 'default_frontend_label' => 'EAN'], as: 'ean'),
    DataFixture(CustomerGroupFixture::class, as: 'trade'),
    DataFixture(
        ProductFixture::class,
        [
            'name' => 'Field satchel',
            'sku' => 'LC-FS-TAN',
            'price' => 10,
            'short_description' => 'A <b>full-grain</b> leather satchel.',
            'ean' => '4062001000010',
            'tier_prices' => [['customer_group_id' => '$trade.id$', 'qty' => 1, 'value' => 7]],
        ],
        as: 'satchel'
    ),
    DataFixture(ReviewFixture::class, ['entity_pk_value' => '$satchel.id$'], as: 'review'),
    DataFixture(ProductFixture::class, ['name' => 'Card sleeve', 'sku' => 'LC-CS-BLK', 'price' => 45], as: 'sleeve'),
    DataFixture(
        ProductFixture::class,
        [
            'name' => 'Waxed tote',
            'sku' => 'LC-WT-OLV',
            'price' => 120,
            'special_price' => 96,
            'special_to_date' => '2099-12-31 00:00:00',
        ],
        as: 'tote'
    ),
    DataFixture(ConfigurableAttributeFixture::class, as: 'size'),
    DataFixture(ProductFixture::class, ['sku' => 'LC-WK-S', 'price' => 310], as: 'small'),
    DataFixture(ProductFixture::class, ['sku' => 'LC-WK-L', 'price' => 290], as: 'large'),
    DataFixture(
        ConfigurableProductFixture::class,
        ['name' => 'Weekender', 'sku' => 'LC-WK', '_options' => ['$size$'], '_links' => ['$small$', '$large$']],
        as: 'weekender'
    ),
]
class ProductTemplateTest extends TestCase
{
    private const TEMPLATE = '/Test/_templates/product.json';
    private const RETURN_POLICY = '{"@type": "MerchantReturnPolicy", "applicableCountry": "{{store.countries}}",'
        . ' "merchantReturnDays": 30}';

    /**
     * @var ObjectManagerInterface
     */
    private ObjectManagerInterface $objectManager;

    /**
     * @var int
     */
    private int $storeId = 0;

    /**
     * @var bool
     */
    private bool $savedReturnPolicy = false;

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
        $this->get(HttpContext::class)->unsValue(CustomerContext::CONTEXT_GROUP);
        $this->get(PageDataPrefetchInterface::class)->reset();
        if ($this->savedReturnPolicy) {
            $connection = $this->get(ResourceConnection::class)->getConnection();
            $connection->delete(
                $connection->getTableName(SnippetResource::TABLE),
                [SnippetResource::CODE . ' = ?' => 'return-policy']
            );
            $this->get(SnippetIncludeResolver::class)->_resetState();
        }
    }

    public function testASimpleProductWithAnEanAndARatedReviewGivesTheFullShape(): void
    {
        $this->rate();
        $this->saveReturnPolicy();

        $output = $this->product('satchel');

        self::assertSame('Product', $output['@type']);
        self::assertSame('Field satchel', $output['name']);
        self::assertSame('A full-grain leather satchel.', $output['description']);
        self::assertSame('LC-FS-TAN', $output['sku']);
        self::assertSame('4062001000010', $output['gtin13']);
        self::assertSame(['@type' => 'Brand', 'name' => 'Linden & Co.'], $output['brand']);
        self::assertArrayNotHasKey('image', $output, 'A product without gallery images has no image.');
        $offers = $this->map($output['offers']);
        self::assertSame('10.00', $offers['price']);
        self::assertSame('USD', $offers['priceCurrency']);
        self::assertSame('https://schema.org/InStock', $offers['availability']);
        self::assertSame($this->productUrl('satchel'), $offers['url']);
        self::assertArrayNotHasKey('priceSpecification', $offers, 'Not on sale.');
        self::assertArrayNotHasKey('priceValidUntil', $offers);
        $policy = $this->map($offers['hasMerchantReturnPolicy']);
        self::assertSame(30, $policy['merchantReturnDays']);
        self::assertArrayNotHasKey('@context', $policy);
        self::assertIsArray($policy['applicableCountry']);
        self::assertIsList($policy['applicableCountry']);
        self::assertContains('US', $policy['applicableCountry']);
        self::assertSame(
            ['@type' => 'AggregateRating', 'ratingValue' => 5, 'reviewCount' => 1],
            $output['aggregateRating']
        );
        self::assertSame(
            [[
                '@type' => 'Review',
                'author' => ['@type' => 'Person', 'name' => 'Nickname'],
                'datePublished' => $this->today(),
                'reviewBody' => 'Review detail',
                'reviewRating' => ['@type' => 'Rating', 'ratingValue' => 5],
            ]],
            $output['review']
        );
    }

    public function testAProductWithoutEanAndReviewsLeavesThemOut(): void
    {
        $output = $this->product('sleeve');

        self::assertSame('Card sleeve', $output['name']);
        self::assertSame('45.00', $this->map($output['offers'])['price']);
        self::assertArrayNotHasKey('gtin13', $output);
        self::assertArrayNotHasKey('aggregateRating', $output, 'No approved review: no aggregate rating.');
        self::assertArrayNotHasKey('review', $output);
    }

    public function testAProductOnSaleCarriesItsEndDateAndTheStrikethroughPrice(): void
    {
        $offers = $this->map($this->product('tote')['offers']);

        self::assertSame('96.00', $offers['price']);
        self::assertSame('2099-12-31', $offers['priceValidUntil']);
        self::assertSame(
            [
                '@type' => 'UnitPriceSpecification',
                'priceType' => 'https://schema.org/StrikethroughPrice',
                'price' => '120.00',
                'priceCurrency' => 'USD',
            ],
            $offers['priceSpecification']
        );
    }

    public function testAConfigurableProductIsPricedByItsCheapestChildAndListsItsVariants(): void
    {
        self::assertSame('290.00', $this->map($this->product('weekender')['offers'])['price']);

        $variants = $this->evaluate(
            '{"hasVariant": {"@each": "product.variants", "sku": "{{variant.sku}}", "price": "{{variant.price}}",'
                . ' "url": "{{variant.url}}", "availability": "{{variant.availability}}"}}',
            'product',
            $this->id('weekender')
        );
        $size = $this->fixture('size');
        $code = $size->getData('attribute_code');
        self::assertIsString($code);
        $base = $this->productUrl('weekender');

        self::assertSame(
            ['hasVariant' => [
                [
                    'sku' => 'LC-WK-S',
                    'price' => '310.00',
                    'url' => $base . '?' . $code . '=' . $this->number($size->getData('option_1')),
                    'availability' => 'https://schema.org/InStock',
                ],
                [
                    'sku' => 'LC-WK-L',
                    'price' => '290.00',
                    'url' => $base . '?' . $code . '=' . $this->number($size->getData('option_2')),
                    'availability' => 'https://schema.org/InStock',
                ],
            ]],
            $variants
        );
    }

    public function testACustomerOfAPricedGroupGetsThatGroupsPriceAndAGuestTheGuestPrice(): void
    {
        $template = '{"price": "{{product.final_price}}"}';
        $context = $this->get(HttpContext::class);
        $context->setValue(CustomerContext::CONTEXT_GROUP, $this->number($this->fixture('trade')->getData('id')), 0);
        $group = $this->engine()->evaluate($template, $this->scope('product', $this->id('satchel')));
        $this->get(PageDataPrefetchInterface::class)->reset();
        $context->setValue(CustomerContext::CONTEXT_GROUP, 0, 0);
        $guest = $this->engine()->evaluate($template, $this->scope('product', $this->id('satchel')));

        self::assertSame(['price' => '7.00'], $group);
        self::assertSame(['price' => '10.00'], $guest);
    }

    public function testTheFieldCatalogListsTheBuiltInsTheAttributesAndTheStoreFieldsTheLibraryReads(): void
    {
        $catalog = $this->get(FieldCatalogInterface::class);
        $paths = array_map(static fn (FieldInterface $field): string => $field->getPath(), $catalog->getFields());

        self::assertSame('product_attributes', $catalog->getField('product.ean')?->getGroupCode());
        self::assertSame('EAN', $catalog->getField('product.ean')->getLabel());
        self::assertSame('product', $catalog->getField('product.image')?->getGroupCode(), 'The main image wins.');
        self::assertSame('price', $catalog->getField('product.special_to_date')?->getGroupCode());
        foreach ([
            'store.name', 'store.url', 'store.logo', 'store.phone', 'store.email', 'store.currency',
            'store.countries', 'store.address.street', 'store.address.postcode', 'store.address.city',
            'store.address.region', 'store.address.country', 'reviews.rating', 'reviews.count', 'review.nickname',
            'image.url', 'page.title', 'page.url', 'category.name', 'crumb.item', 'variant.url', 'listed.url',
        ] as $path) {
            self::assertContains($path, $paths);
        }
        self::assertSame(count($paths), count(array_unique($paths)), 'Each path is listed once.');
    }

    /**
     * The design's Product template evaluated for a product, as the storefront of the default store view
     *
     * @param string $product
     * @return array<string, mixed>
     */
    private function product(string $product): array
    {
        $engineDirectory = $this->get(ComponentRegistrar::class)
            ->getPath(ComponentRegistrar::MODULE, 'Hryvinskyi_JsonTemplate');
        $source = file_get_contents((string) $engineDirectory . self::TEMPLATE);
        self::assertNotFalse($source);

        return $this->map($this->evaluate($source, 'product', $this->id($product)));
    }

    /**
     * A template evaluated for a page, as the storefront of the default store view
     *
     * @param string $template
     * @param string $kind
     * @param int $entityId
     * @return mixed
     */
    private function evaluate(string $template, string $kind, int $entityId): mixed
    {
        return $this->get(FrontendEmulationInterface::class)->run(
            $this->storeId,
            fn (): mixed => $this->engine()->evaluate($template, $this->scope($kind, $entityId))
        );
    }

    /**
     * The scope of a page of the default store view
     *
     * @param string $kind
     * @param int $entityId
     * @return \Hryvinskyi\JsonTemplate\Api\ScopeInterface
     */
    private function scope(string $kind, int $entityId): \Hryvinskyi\JsonTemplate\Api\ScopeInterface
    {
        return $this->get(PageScopeFactoryInterface::class)->create(
            $this->get(PageEntityFactoryInterface::class)->create($kind, $entityId, $this->storeId)
        );
    }

    /**
     * Give the review a vote of five stars in a rating of the store view and aggregate it
     *
     * @return void
     */
    private function rate(): void
    {
        $connection = $this->get(ResourceConnection::class)->getConnection();
        $option = $connection->fetchRow(
            $connection->select()
                ->from(['option' => $connection->getTableName('rating_option')], ['option_id', 'rating_id'])
                ->join(['store' => $connection->getTableName('rating_store')], 'store.rating_id = option.rating_id', [])
                ->join(['rating' => $connection->getTableName('rating')], 'rating.rating_id = option.rating_id', [])
                ->where('store.store_id = ?', $this->storeId)
                ->where('rating.is_active = 1')
                ->where('option.value = 5')
                ->limit(1)
        );
        self::assertIsArray($option, 'The test database has an active rating in the default store view.');
        $review = $this->fixture('review');
        self::assertInstanceOf(Review::class, $review);
        $rating = $this->get(RatingFactory::class)->create();
        self::assertInstanceOf(Rating::class, $rating);
        $rating->setId($this->number($option['rating_id']));
        $rating->setData('review_id', $this->number($review->getId()));
        $rating->addOptionVote($this->number($option['option_id']), $this->id('satchel'));
        $review->aggregate();
    }

    /**
     * Save the building block the Product template includes
     *
     * @return void
     */
    private function saveReturnPolicy(): void
    {
        $snippet = $this->get(SnippetFactoryInterface::class)->draft('return-policy', 'Return policy', true);
        $snippet->changeTemplate(self::RETURN_POLICY, $this->get(TemplateTypeInterface::class));
        $this->get(SnippetRootRepositoryInterface::class)->save($snippet);
        $this->savedReturnPolicy = true;
        $this->get(SnippetIncludeResolver::class)->_resetState();
    }

    /**
     * The canonical address of a fixture product in the default store view
     *
     * @param string $product
     * @return string
     */
    private function productUrl(string $product): string
    {
        $connection = $this->get(ResourceConnection::class)->getConnection();
        $path = $connection->fetchOne(
            $connection->select()
                ->from($connection->getTableName('url_rewrite'), ['request_path'])
                ->where('entity_type = ?', 'product')
                ->where('entity_id = ?', $this->id($product))
                ->where('store_id = ?', $this->storeId)
                ->where('metadata IS NULL')
        );
        self::assertNotSame('', $path, 'The product has its own address.');
        $store = $this->get(StoreManagerInterface::class)->getStore($this->storeId);
        self::assertInstanceOf(Store::class, $store);

        return rtrim((string) $store->getBaseUrl(), '/') . '/' . $path;
    }

    /**
     * Today in the default store view's time zone
     *
     * @return string
     */
    private function today(): string
    {
        return $this->get(\Magento\Framework\Stdlib\DateTime\TimezoneInterface::class)
            ->scopeDate($this->storeId)
            ->format('Y-m-d');
    }

    /**
     * A value narrowed to an object of the output
     *
     * @param mixed $value
     * @return array<string, mixed>
     */
    private function map(mixed $value): array
    {
        self::assertIsArray($value);
        $map = [];
        foreach ($value as $key => $item) {
            $map[(string) $key] = $item;
        }

        return $map;
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
     * A fixture's ID
     *
     * @param string $name
     * @return int
     */
    private function id(string $name): int
    {
        return $this->entityId($this->fixture($name));
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

    /**
     * A numeric value as an integer
     *
     * @param mixed $value
     * @return int
     */
    private function number(mixed $value): int
    {
        self::assertIsNumeric($value);

        return (int) $value;
    }
}
