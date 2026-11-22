<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Test\Integration\Controller\Adminhtml\Snippets;

use Hryvinskyi\RichSnippets\Api\ConditionTreePrinterInterface;
use Hryvinskyi\RichSnippets\Api\LibraryInterface;
use Hryvinskyi\RichSnippets\Api\PageDataPrefetchInterface;
use Hryvinskyi\RichSnippets\Api\SnippetRepositoryInterface;
use Hryvinskyi\RichSnippets\Model\Admin\AdminResources;
use Hryvinskyi\RichSnippets\Model\Engine\SnippetIncludeResolver;
use Hryvinskyi\RichSnippets\Model\ResourceModel\IdempotencyEntry;
use Hryvinskyi\RichSnippets\Model\ResourceModel\Snippet as SnippetResource;
use Hryvinskyi\RichSnippets\Test\Integration\Fixture\AsksAsTheSnippetsPage;
use Magento\Catalog\Test\Fixture\Attribute as AttributeFixture;
use Magento\Catalog\Test\Fixture\Product as ProductFixture;
use Magento\ConfigurableProduct\Test\Fixture\Attribute as ConfigurableAttributeFixture;
use Magento\ConfigurableProduct\Test\Fixture\Product as ConfigurableProductFixture;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Model\AbstractModel;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Magento\TestFramework\Fixture\AppArea;
use Magento\TestFramework\Fixture\AppIsolation;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Fixture\DataFixtureStorageManager;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\TestCase\AbstractBackendController;

/**
 * The library's Product snippet previewed for the design's samples through the real admin, as the editor sends it
 *
 * The products stand for the samples: a simple product with an EAN, one on sale until a fixed day, one with neither
 * EAN nor reviews, and a configurable product the snippet's condition leaves out. The preview is the storefront's:
 * the product addresses are the store view's, not the admin's. The empty properties are traced to the fields they
 * read, and the Checks panel says what is left out and why a page gets no block.
 *
 * The price index is filled once a product's save commits, so the test runs outside an isolating transaction; the
 * fixtures remove themselves and the test removes the snippets the library adds and the key it adds them with. The
 * preview runs any template it is sent against any page, so the parent class's access tests ask for the permission to
 * change snippets.
 */
#[
    AppArea('adminhtml'),
    AppIsolation(true),
    DbIsolation(false),
    DataFixture(AttributeFixture::class, ['attribute_code' => 'ean', 'default_frontend_label' => 'EAN'], as: 'ean'),
    DataFixture(
        ProductFixture::class,
        ['name' => 'Field satchel', 'sku' => 'RS-PV-SATCHEL', 'price' => 240, 'ean' => '4062001000010'],
        as: 'satchel'
    ),
    DataFixture(
        ProductFixture::class,
        [
            'name' => 'Waxed tote',
            'sku' => 'RS-PV-TOTE',
            'price' => 120,
            'special_price' => 96,
            'special_to_date' => '2099-12-31 00:00:00',
        ],
        as: 'tote'
    ),
    DataFixture(ProductFixture::class, ['name' => 'Card sleeve', 'sku' => 'RS-PV-SLEEVE', 'price' => 45], as: 'sleeve'),
    DataFixture(ConfigurableAttributeFixture::class, as: 'size'),
    DataFixture(ProductFixture::class, ['sku' => 'RS-PV-WK-S', 'price' => 310], as: 'small'),
    DataFixture(
        ConfigurableProductFixture::class,
        ['name' => 'Weekender', 'sku' => 'RS-PV-WK', '_options' => ['$size$'], '_links' => ['$small$']],
        as: 'weekender'
    ),
]
class EditorPreviewEndpointTest extends AbstractBackendController
{
    use AsksAsTheSnippetsPage;

    private const PREVIEW = 'backend/richsnippets/snippets/preview';

    /**
     * @var string
     */
    protected $resource = AdminResources::SNIPPETS;

    /**
     * @var string
     */
    protected $uri = self::PREVIEW;

    /**
     * @var string
     */
    protected $httpMethod = HttpRequest::METHOD_POST;

    /**
     * @var list<int> the snippets the library added for the test
     */
    private array $added = [];

    /**
     * @var array<string, mixed> the library's Product snippet as the editor sends it
     */
    private array $draft = [];

    /**
     * @var string the idempotency key the library's addition was made with
     */
    private string $key = '';

    /**
     * Add the library's Product snippet, its EAN read from the attribute, and send requests as the browser does
     *
     * @inheritDoc
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->prefetch()->reset();
        $library = Bootstrap::getObjectManager()->get(LibraryInterface::class);
        self::assertInstanceOf(LibraryInterface::class, $library);
        $this->key = 'editor-preview-' . uniqid();
        $added = $library->add('product', ['gtin' => 'attribute:ean'], $this->key);
        foreach ($added as $snippet) {
            $this->added[] = $snippet->getId();
        }
        $this->resolver()->_resetState();
        $repository = Bootstrap::getObjectManager()->get(SnippetRepositoryInterface::class);
        self::assertInstanceOf(SnippetRepositoryInterface::class, $repository);
        $product = $repository->get($added[count($added) - 1]->getId());
        $printer = Bootstrap::getObjectManager()->get(ConditionTreePrinterInterface::class);
        self::assertInstanceOf(ConditionTreePrinterInterface::class, $printer);
        $this->draft = [
            'id' => $product->getId(),
            'name' => $product->getName(),
            'template' => $product->getTemplate(),
            'isBlock' => false,
            'active' => false,
            'pageKinds' => $product->getPageKinds(),
            'conditions' => $printer->print($product->getConditions()),
            'storeIds' => $product->getStoreIds(),
        ];
        $this->askAsTheBrowser(HttpRequest::METHOD_POST, ['snippet' => $this->draft]);
    }

    /**
     * Remove the snippets the library added and the key it added them with
     *
     * @inheritDoc
     */
    protected function tearDown(): void
    {
        $resource = Bootstrap::getObjectManager()->get(ResourceConnection::class);
        self::assertInstanceOf(ResourceConnection::class, $resource);
        if ($this->added !== []) {
            $resource->getConnection()->delete(
                $resource->getTableName(SnippetResource::TABLE),
                [SnippetResource::ID . ' IN (?)' => $this->added]
            );
        }
        $resource->getConnection()->delete(
            $resource->getTableName(IdempotencyEntry::TABLE),
            [IdempotencyEntry::KEY . ' = ?' => $this->key]
        );
        $this->resolver()->_resetState();
        $this->prefetch()->reset();
        parent::tearDown();
    }

    public function testTheSatchelGetsItsBlockWithItsEanAndTheStorefrontsAddress(): void
    {
        $preview = $this->preview('satchel');

        self::assertSame('output', $preview['state']);
        $output = $this->map($preview['output']);
        self::assertSame('Product', $output['@type']);
        self::assertSame('Field satchel', $output['name']);
        self::assertSame('4062001000010', $output['gtin13']);
        $offers = $this->map($output['offers']);
        self::assertSame('240.00', $offers['price']);
        self::assertSame($this->productUrl('satchel'), $offers['url'], 'The address is the storefront\'s.');
        self::assertArrayNotHasKey('priceSpecification', $offers, 'Not on sale.');
        self::assertSame('Field satchel', $this->at($preview, 'sample', 'label'));
        self::assertSame($this->productUrl('satchel'), $this->at($preview, 'sample', 'url'));
        self::assertNull($preview['message']);
        self::assertContains('skipped', $this->outcomes($preview, '$.offers.priceSpecification'));
    }

    public function testTheToteOnSaleCarriesTheStrikethroughPrice(): void
    {
        $offers = $this->map($this->map($this->preview('tote')['output'])['offers']);

        self::assertSame('96.00', $offers['price']);
        self::assertSame('2099-12-31', $offers['priceValidUntil']);
        $strikethrough = $this->map($offers['priceSpecification']);
        self::assertSame('https://schema.org/StrikethroughPrice', $strikethrough['priceType']);
        self::assertSame('120.00', $strikethrough['price']);
    }

    public function testTheSleevesGapsAreTracedToTheFieldsTheyRead(): void
    {
        $preview = $this->preview('sleeve');

        $output = $this->map($preview['output']);
        self::assertArrayNotHasKey('gtin13', $output);
        self::assertArrayNotHasKey('aggregateRating', $output);
        self::assertArrayNotHasKey('review', $output);
        self::assertSame(['empty'], $this->outcomes($preview, '$.gtin13'));
        self::assertSame(['product.ean'], $this->sources($preview, '$.gtin13'));
        self::assertSame(['reviews.rating'], $this->sources($preview, '$.aggregateRating.ratingValue'));
        $titles = [];
        foreach ((array) $preview['checks'] as $row) {
            self::assertIsArray($row);
            self::assertIsString($row['title'] ?? null);
            $titles[] = $row['title'];
        }
        $leftOut = array_values(array_filter(
            $titles,
            static fn (string $title): bool => str_starts_with($title, 'Left out for Card sleeve: ')
        ));
        self::assertCount(1, $leftOut);
        self::assertStringContainsString('gtin13', $leftOut[0]);
        self::assertStringContainsString('aggregateRating', $leftOut[0]);
    }

    public function testTheWeekenderIsLeftOutByTheConditionAndTheReasonSaysWhy(): void
    {
        $preview = $this->preview('weekender');

        self::assertSame('conditions_not_met', $preview['state']);
        self::assertNull($preview['output']);
        self::assertSame('Not output for Weekender', $this->at($preview, 'message', 'title'));
        $reason = $this->at($preview, 'checks', 0, 'text');
        self::assertIsString($reason);
        self::assertStringStartsWith(
            'The conditions in Pages ask for “Product type is not Configurable Product”.',
            $reason
        );
        self::assertSame(
            [
                'label' => 'See the conditions',
                'path' => null,
                'icon' => 'arrow_forward',
                'value' => null,
                'before' => [],
            ],
            $this->at($preview, 'checks', 0, 'action')
        );
        self::assertSame(
            'This page does not meet the conditions under “Only when”, in Pages.',
            $this->at($preview, 'message', 'text')
        );
    }

    public function testADraftWithoutItsImageIsOfferedTheLibrarysImageBackInItsPlace(): void
    {
        self::assertIsString($this->draft['template']);
        $template = json_decode($this->draft['template'], true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($template);
        unset($template['image']);
        $draft = array_replace($this->draft, ['template' => json_encode($template, JSON_THROW_ON_ERROR)]);

        $preview = $this->post(self::PREVIEW, ['snippet' => $draft, 'sample' => 'product:' . $this->id('satchel')]);

        $add = $this->at($preview, 'checks', 0, 'action');
        self::assertIsArray($add, 'The first check puts the required image back.');
        self::assertSame('Add image', $add['label'] ?? null);
        self::assertSame('image', $add['path'] ?? null);
        self::assertSame(['brand', 'color', 'offers', 'aggregateRating', 'review'], $add['before'] ?? null);
        self::assertIsString($add['value'] ?? null);
        self::assertSame(
            ['@each' => 'product.gallery', '@value' => '{{image.url}}'],
            json_decode($add['value'], true, 512, JSON_THROW_ON_ERROR)
        );
    }

    public function testAnImageTheDraftHasIsNotPutBackWhenTheSatchelHasNoPicture(): void
    {
        $preview = $this->preview('satchel');

        self::assertSame('Google needs image for a merchant listing', $this->at($preview, 'checks', 0, 'title'));
        self::assertNull($this->at($preview, 'checks', 0, 'action'), 'The page has no picture; the draft is fine.');
    }

    public function testATemplateWithoutATypeShowsNothingYetAndAnUnknownSampleIsRefused(): void
    {
        $draft = array_replace($this->draft, ['template' => '{"name": "{{product.name}}"}']);

        $preview = $this->post(self::PREVIEW, ['snippet' => $draft, 'sample' => 'product:' . $this->id('sleeve')]);
        $refused = $this->post(self::PREVIEW, ['snippet' => $draft, 'sample' => 'blog:1'], 400);

        self::assertSame('no_type', $preview['state']);
        self::assertSame('Choose a type first', $this->at($preview, 'checks', 0, 'title'));
        self::assertSame(['error' => 'sample'], $refused);
    }

    /**
     * The draft previewed for a fixture product in the default store view
     *
     * @param string $product
     * @return array<mixed>
     */
    private function preview(string $product): array
    {
        $this->prefetch()->reset();

        return $this->post(self::PREVIEW, ['snippet' => $this->draft, 'sample' => 'product:' . $this->id($product)]);
    }

    /**
     * The outcomes the trace recorded at a path
     *
     * @param array<mixed> $preview
     * @param string $path
     * @return list<mixed>
     */
    private function outcomes(array $preview, string $path): array
    {
        return array_column($this->entries($preview, $path), 'outcome');
    }

    /**
     * The sources the trace recorded at a path, the last time
     *
     * @param array<mixed> $preview
     * @param string $path
     * @return mixed
     */
    private function sources(array $preview, string $path): mixed
    {
        $entries = $this->entries($preview, $path);
        self::assertNotSame([], $entries, sprintf('The trace has %s.', $path));

        return $entries[count($entries) - 1]['sources'] ?? null;
    }

    /**
     * The trace entries at a path
     *
     * @param array<mixed> $preview
     * @param string $path
     * @return list<array<mixed>>
     */
    private function entries(array $preview, string $path): array
    {
        $entries = [];
        foreach ((array) $preview['trace'] as $entry) {
            if (is_array($entry) && ($entry['path'] ?? null) === $path) {
                $entries[] = $entry;
            }
        }

        return $entries;
    }

    /**
     * The canonical storefront address of a fixture product in the default store view
     *
     * @param string $product
     * @return string
     */
    private function productUrl(string $product): string
    {
        $resource = Bootstrap::getObjectManager()->get(ResourceConnection::class);
        self::assertInstanceOf(ResourceConnection::class, $resource);
        $connection = $resource->getConnection();
        $store = $this->defaultStore();
        $path = (string) $connection->fetchOne(
            $connection->select()
                ->from($connection->getTableName('url_rewrite'), ['request_path'])
                ->where('entity_type = ?', 'product')
                ->where('entity_id = ?', $this->id($product))
                ->where('store_id = ?', (int) $store->getId())
                ->where('metadata IS NULL')
        );
        self::assertNotSame('', $path, 'The product has its own address.');

        return rtrim((string) $store->getBaseUrl(), '/') . '/' . $path;
    }

    /**
     * @return Store
     */
    private function defaultStore(): Store
    {
        $storeManager = Bootstrap::getObjectManager()->get(StoreManagerInterface::class);
        self::assertInstanceOf(StoreManagerInterface::class, $storeManager);
        $store = $storeManager->getDefaultStoreView();
        self::assertInstanceOf(Store::class, $store);

        return $store;
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
     * A fixture's ID
     *
     * @param string $name
     * @return int
     */
    private function id(string $name): int
    {
        $fixture = DataFixtureStorageManager::getStorage()->get($name);
        self::assertInstanceOf(AbstractModel::class, $fixture);
        $id = $fixture->getId();
        self::assertIsNumeric($id);

        return (int) $id;
    }

    /**
     * @return PageDataPrefetchInterface
     */
    private function prefetch(): PageDataPrefetchInterface
    {
        $prefetch = Bootstrap::getObjectManager()->get(PageDataPrefetchInterface::class);
        self::assertInstanceOf(PageDataPrefetchInterface::class, $prefetch);

        return $prefetch;
    }

    /**
     * @return SnippetIncludeResolver
     */
    private function resolver(): SnippetIncludeResolver
    {
        $resolver = Bootstrap::getObjectManager()->get(SnippetIncludeResolver::class);
        self::assertInstanceOf(SnippetIncludeResolver::class, $resolver);

        return $resolver;
    }
}
