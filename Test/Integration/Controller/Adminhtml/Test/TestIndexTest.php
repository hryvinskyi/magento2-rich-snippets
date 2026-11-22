<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Test\Integration\Controller\Adminhtml\Test;

use Hryvinskyi\RichSnippets\Model\Admin\AdminResources;
use Hryvinskyi\RichSnippets\Model\Admin\Check\CheckAnswers;
use Hryvinskyi\RichSnippets\Model\Cache\Type\RichSnippetsCache;
use Hryvinskyi\RichSnippets\Model\Check\RequestedChecks;
use Hryvinskyi\RichSnippets\Model\Check\Storage\CheckTables;
use Hryvinskyi\RichSnippets\Model\ResourceModel\Snippet as SnippetResource;
use Hryvinskyi\RichSnippets\Test\Integration\Fixture\AsksAsTheSnippetsPage;
use Hryvinskyi\RichSnippets\Test\Integration\Fixture\StoresTestSnippets;
use Magento\Backend\Model\Url;
use Magento\Catalog\Test\Fixture\Virtual as VirtualProductFixture;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Model\AbstractModel;
use Magento\Store\Model\StoreManagerInterface;
use Magento\TestFramework\Fixture\AppArea;
use Magento\TestFramework\Fixture\AppIsolation;
use Magento\TestFramework\Fixture\Config;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Fixture\DataFixtureStorageManager;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\TestCase\AbstractBackendController;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The Test page, rendered by the real admin: the island root with its document — the shell with the Test tab
 * current, the page's phrases, its seven endpoints and the screens it links to with their secret keys, the form key,
 * the right to run tests — and its first data: the mode and the page the address names, where the catalog check
 * stands and the findings of the last one, with the finding the address names
 *
 * The parent class adds the access tests for the module's view permission: the page opens for whoever may see the
 * module, and only running tests needs more. The island's built file is not run here.
 */
#[
    AppArea('adminhtml'),
    AppIsolation(true),
    DbIsolation(true),
    DataFixture(VirtualProductFixture::class, ['sku' => 'rs-test-page', 'name' => 'Test page bag'], as: 'product'),
]
class TestIndexTest extends AbstractBackendController
{
    use AsksAsTheSnippetsPage;
    use StoresTestSnippets;

    private const PAGE = 'backend/richsnippets/test/index';
    private const REQUEST = 'backend/richsnippets/test/checkRequest';
    private const ISLAND = 'rich-snippets-test';
    private const CONTRACTS = __DIR__ . '/../../../../_contract/admin-pages/';
    private const ENDPOINTS = [
        'search', 'run', 'checkStatus', 'checkRequest', 'findings', 'findingItems', 'findingsCsv',
    ];
    private const ROUTES = [
        'hryvinskyi_richsnippets/snippets/edit', 'adminhtml/system_config/edit', 'catalog/product/index',
        'catalog/product/edit', 'catalog/category/edit', 'cms/page/edit',
    ];

    /**
     * @var string
     */
    protected $resource = AdminResources::VIEW;

    /**
     * @var string
     */
    protected $uri = self::PAGE;

    /**
     * @var string
     */
    protected $httpMethod = HttpRequest::METHOD_GET;

    /**
     * Start every test with no snippet and no check run, whatever the database kept
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $resource = $this->testObjectManager()->get(ResourceConnection::class);
        self::assertInstanceOf(ResourceConnection::class, $resource);
        $resource->getConnection()->delete($resource->getTableName(SnippetResource::TABLE));
        $resource->getConnection()->delete($resource->getTableName(CheckTables::RUN));
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

    #[Config(Url::XML_PATH_USE_SECURE_KEY, '1')]
    public function testThePageCarriesTheIslandWithItsDocumentAndTheFirstData(): void
    {
        $page = $this->openPage();

        self::assertSame('Test', $this->at($page, 'shell', 'page'));
        self::assertSame(
            [['overview', false], ['snippets', false], ['library', false], ['test', true], ['settings', false]],
            array_map(
                static fn (array $tab): array => [$tab['code'] ?? null, $tab['current'] ?? null],
                $this->listAt($page, 'shell', 'nav')
            )
        );
        foreach (self::ENDPOINTS as $name) {
            self::assertMatchesRegularExpression(
                '#/richsnippets/test/' . $name . '/key/[0-9a-f]+/#',
                $this->stringAt($page, 'urls', $name),
                $name
            );
        }
        foreach (self::ROUTES as $route) {
            self::assertMatchesRegularExpression('#/key/[0-9a-f]+/#', $this->stringAt($page, 'routes', $route), $route);
        }
        self::assertNotSame('', $this->stringAt($page, 'formKey'));
        self::assertTrue($this->at($page, 'permissions', 'test'));
        $data = $this->at($page, 'data');
        self::assertIsArray($data);
        $contract = $this->contract('test-page-data.json')['page'] ?? null;
        self::assertIsArray($contract);
        self::assertSame(array_keys($contract), array_keys($data), 'the data has the contract\'s fields');
        self::assertSame('page', $data['mode']);
        self::assertSame($this->defaultStoreId(), $data['store']);
        self::assertNull($data['catalogStore']);
        self::assertNull($data['finding']);
        self::assertSame(['idle', 'none'], [$this->at($data, 'status', 'state'), $this->at($data, 'status', 'tone')]);
        self::assertSame([], $this->at($data, 'findings', 'findings'));
        self::assertMatchesRegularExpression(
            '/^It runs every night at \d\d:\d\d and after an import/',
            $this->stringAt($data, 'schedule')
        );
    }

    public function testThePageOpensTheHomePageWhenTheAddressNamesNoPage(): void
    {
        $page = $this->at($this->openPage(), 'data', 'page');

        self::assertIsArray($page);
        self::assertSame(['ref', 'kind', 'label', 'path'], array_keys($page));
        self::assertSame('home', $page['kind']);
        self::assertMatchesRegularExpression('/^home:\d+$/', $this->stringAt($page, 'ref'));
    }

    public function testThePageOpensTheProductTheAddressNames(): void
    {
        $id = $this->productId();

        $page = $this->at($this->openPage(['page' => 'product:' . $id]), 'data', 'page');

        self::assertSame(['product:' . $id, 'product', 'Test page bag'], [
            $this->at($page, 'ref'), $this->at($page, 'kind'), $this->at($page, 'label'),
        ]);
    }

    /**
     * @phpstan-return array<string, array{0: array<string, string>,
     *     1: array{ref: null, kind: string, label: string, path: string}|null}>
     */
    public static function addresses(): array
    {
        $cart = ['ref' => null, 'kind' => 'other', 'label' => '/checkout/cart', 'path' => '/checkout/cart'];

        return [
            'a storefront path' => [['path' => '/checkout/cart'], $cart],
            'a path wins over a page' => [['path' => '/checkout/cart', 'page' => 'home'], $cart],
            'an address with a host is no path' => [['path' => 'https://elsewhere.example/'], null],
        ];
    }

    /**
     * @param array<string, string> $query
     * @param array{ref: null, kind: string, label: string, path: string}|null $expected null for the home page
     */
    #[DataProvider('addresses')]
    public function testThePageOpensTheStorefrontPathTheAddressNames(array $query, ?array $expected): void
    {
        $page = $this->at($this->openPage($query), 'data', 'page');

        if ($expected === null) {
            self::assertSame('home', $this->at($page, 'kind'));
            return;
        }
        self::assertSame($expected, $page);
    }

    public function testAStoreViewTheShopDoesNotHaveIsTakenAsNoneNamed(): void
    {
        $data = $this->at($this->openPage(['store' => '9999']), 'data');

        self::assertSame($this->defaultStoreId(), $this->at($data, 'store'));
        self::assertNull($this->at($data, 'catalogStore'));
    }

    public function testTheCatalogModeReadsTheFindingsOfTheStoreViewTheAddressNames(): void
    {
        $this->checkTheCatalog();
        $storeId = $this->defaultStoreId();

        $data = $this->at($this->openPage(['mode' => 'catalog', 'store' => (string) $storeId]), 'data');

        self::assertSame(['catalog', $storeId], [$this->at($data, 'mode'), $this->at($data, 'catalogStore')]);
        self::assertSame($storeId, $this->at($data, 'findings', 'store'));
        self::assertNotSame([], $this->at($data, 'findings', 'findings'));
        self::assertSame('idle', $this->at($data, 'status', 'state'));
        self::assertNull($this->at($data, 'finding'));
    }

    public function testTheCatalogModeOpensTheFindingTheAddressNamesWhenTheLastCheckFoundIt(): void
    {
        $this->checkTheCatalog();
        $key = $this->firstFindingKey();

        $data = $this->at($this->openPage(['mode' => 'catalog', 'finding' => $key]), 'data');

        self::assertNull($this->at($data, 'catalogStore'));
        self::assertSame($key, $this->at($data, 'finding'));
    }

    public function testAFindingTheLastCheckDidNotFindOpensNothing(): void
    {
        $this->checkTheCatalog();

        $data = $this->at($this->openPage(['mode' => 'catalog', 'finding' => 'no-such-finding']), 'data');

        self::assertNull($this->at($data, 'finding'));
    }

    public function testThePageCarriesExactlyThePhrasesItsIslandAsksFor(): void
    {
        $phrases = $this->at($this->openPage(), 'phrases');

        self::assertIsArray($phrases);
        self::assertSame($this->contract('test-phrases.json')['keys'] ?? null, array_keys($phrases));
        self::assertSame('Whole catalog', $phrases['test.mode.catalog'] ?? null);
    }

    public function testWithoutTheRightToRunTestsThePageStillOpensAndSaysSo(): void
    {
        $this->deny(AdminResources::TEST);

        $page = $this->openPage(['mode' => 'catalog']);

        self::assertFalse($this->at($page, 'permissions', 'test'));
        self::assertSame('catalog', $this->at($page, 'data', 'mode'));
        self::assertContains(
            'test',
            array_column($this->listAt($page, 'shell', 'nav'), 'code'),
            'the Test tab opens the findings for whoever may see the module'
        );
    }

    /**
     * Store a product snippet for virtual products, ask for a check and run it as the minute job does
     *
     * @return void
     */
    private function checkTheCatalog(): void
    {
        $this->storeSnippet('rs-test-page-product', 'Test page product', [
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => '{{product.name}}',
            'image' => '{{product.image}}',
            'offers' => ['@type' => 'Offer', 'price' => '10.00', 'priceCurrency' => 'EUR'],
        ], ['kinds' => ['product'], 'conditions' => [['product_type', '==', 'virtual']], 'active' => true]);
        $this->cache()->clean();
        $this->post(self::REQUEST, []);
        $checks = $this->testObjectManager()->get(RequestedChecks::class);
        self::assertInstanceOf(RequestedChecks::class, $checks);
        self::assertNotNull($checks->runNext(), 'The requested check ran.');
    }

    /**
     * The key of the first finding the last check made over every store view
     *
     * @return string
     */
    private function firstFindingKey(): string
    {
        $answers = $this->testObjectManager()->get(CheckAnswers::class);
        self::assertInstanceOf(CheckAnswers::class, $answers);
        $key = $answers->findings([])['findings'][0]['key'] ?? null;
        self::assertIsString($key, 'The check found something.');

        return $key;
    }

    /**
     * Open the page as an admin does and read its island's document
     *
     * @param array<string, string> $query
     * @return array<mixed> The page's part of the document
     */
    private function openPage(array $query = []): array
    {
        $this->newExchange();
        $this->httpRequest()->setMethod(HttpRequest::METHOD_GET);
        foreach ($query as $name => $value) {
            $this->httpRequest()->setQueryValue($name, $value);
        }
        $this->follow(self::PAGE);

        $html = (string) $this->httpResponse()->getBody();
        self::assertSame(200, $this->httpResponse()->getHttpResponseCode());
        $xpath = $this->parse($html);
        $root = $this->element($xpath, '//div[@data-role="' . self::ISLAND . '"]');
        self::assertStringContainsString('rich-snippets-page', $root->getAttribute('class'));
        self::assertSame(
            'The test could not be loaded. Reload the page to try again.',
            $root->getAttribute('data-failed-text')
        );
        self::assertStringContainsString('Hryvinskyi_RichSnippets/js/admin-pages/test', $html);
        self::assertStringContainsString('Hryvinskyi_RichSnippets/css/admin-pages', $html);
        $document = json_decode(
            $this->element($xpath, '//script[@data-role="' . self::ISLAND . '-config"]')->textContent,
            true
        );
        $page = $this->at($document, 'page');
        self::assertIsArray($page);

        return $page;
    }

    /**
     * The shop's default store view
     *
     * @return int
     */
    private function defaultStoreId(): int
    {
        $stores = $this->testObjectManager()->get(StoreManagerInterface::class);
        self::assertInstanceOf(StoreManagerInterface::class, $stores);

        return (int) $stores->getDefaultStoreView()?->getId();
    }

    /**
     * The fixture product's ID
     *
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
     * A contract file of the admin pages, decoded
     *
     * @param string $file
     * @return array<mixed>
     */
    private function contract(string $file): array
    {
        $contract = json_decode((string) file_get_contents(self::CONTRACTS . $file), true);
        self::assertIsArray($contract);

        return $contract;
    }

    /**
     * The list at a path of names
     *
     * @param mixed $document
     * @param string|int ...$path
     * @return list<array<mixed>>
     */
    private function listAt(mixed $document, string|int ...$path): array
    {
        $value = $this->at($document, ...$path);
        self::assertIsArray($value);
        $items = [];
        foreach ($value as $item) {
            self::assertIsArray($item);
            $items[] = $item;
        }

        return $items;
    }

    /**
     * The text at a path of names
     *
     * @param mixed $document
     * @param string|int ...$path
     * @return string
     */
    private function stringAt(mixed $document, string|int ...$path): string
    {
        $value = $this->at($document, ...$path);
        self::assertIsString($value, implode('.', $path));

        return $value;
    }

    /**
     * @param string $html
     * @return \DOMXPath
     */
    private function parse(string $html): \DOMXPath
    {
        $document = new \DOMDocument();
        $errors = libxml_use_internal_errors(true);
        $document->loadHTML($html);
        libxml_clear_errors();
        libxml_use_internal_errors($errors);

        return new \DOMXPath($document);
    }

    /**
     * @param \DOMXPath $xpath
     * @param string $query
     * @return \DOMElement
     */
    private function element(\DOMXPath $xpath, string $query): \DOMElement
    {
        $nodes = $xpath->query($query);
        self::assertInstanceOf(\DOMNodeList::class, $nodes);
        $node = $nodes->item(0);
        self::assertInstanceOf(\DOMElement::class, $node, $query);

        return $node;
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
