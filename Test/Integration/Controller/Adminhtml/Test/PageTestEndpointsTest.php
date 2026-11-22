<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Test\Integration\Controller\Adminhtml\Test;

use Hryvinskyi\RichSnippets\Api\Data\ReadFailure;
use Hryvinskyi\RichSnippets\Api\Exception\StorefrontReadException;
use Hryvinskyi\RichSnippets\Model\Admin\AdminResources;
use Hryvinskyi\RichSnippets\Model\Storefront\Reading\HttpStorefrontReader;
use Hryvinskyi\RichSnippets\Test\Integration\Fixture\AsksAsTheSnippetsPage;
use Hryvinskyi\RichSnippets\Test\Integration\Fixture\FixedStorefrontReader;
use Magento\Catalog\Test\Fixture\Product as ProductFixture;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\Model\AbstractModel;
use Magento\Store\Model\StoreManagerInterface;
use Magento\TestFramework\Fixture\AppArea;
use Magento\TestFramework\Fixture\AppIsolation;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Fixture\DataFixtureStorageManager;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\TestCase\AbstractBackendController;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The page test's endpoints: the page picker finds the store view's pages and offers a path as any address, the
 * test answers what the page carries or why it could not be read, an address with a host or a page the store view
 * does not have is refused, and only a role allowed to run tests reaches either
 *
 * The storefront reader is replaced for each test by one that answers without the network.
 */
#[
    AppArea('adminhtml'),
    AppIsolation(true),
    DbIsolation(true),
    DataFixture(ProductFixture::class, ['sku' => 'rs-endpoint-satchel', 'name' => 'Endpoint Satchel'], as: 'product'),
]
class PageTestEndpointsTest extends AbstractBackendController
{
    use AsksAsTheSnippetsPage;

    private const SEARCH = 'backend/richsnippets/test/search';
    private const RUN = 'backend/richsnippets/test/run';
    private const NO_ACCESS = 403;
    private const CART = '<html><body><div itemscope itemtype="https://schema.org/Organization">'
        . '<span itemprop="name">Linden</span></div>'
        . '<div itemscope itemtype="https://schema.org/Product"><span itemprop="name">Satchel</span></div>'
        . '<script type="application/ld+json">{"@type":"WebSite","name":"Linden"}</script></body></html>';

    /**
     * @var string
     */
    protected $resource = AdminResources::TEST;

    /**
     * @var string
     */
    protected $uri = self::SEARCH;

    /**
     * @var string
     */
    protected $httpMethod = HttpRequest::METHOD_GET;

    public function testThePickerFindsProductsByNameAndOffersAPathAsAnyAddress(): void
    {
        $byName = $this->read(self::SEARCH, ['query' => 'Endpoint Sat', 'store' => (string) $this->storeId()]);
        $byPath = $this->read(self::SEARCH, ['query' => '/checkout/cart#top', 'store' => (string) $this->storeId()]);

        self::assertContains('product:' . $this->productId(), array_column($this->list($byName, 'pages'), 'ref'));
        self::assertArrayHasKey('address', $byName);
        self::assertNull($byName['address']);
        self::assertSame('/checkout/cart', $byPath['address'] ?? null);
    }

    public function testAPathIsTestedAndItsBlocksAndThemeMarkupAnswered(): void
    {
        $reader = $this->readerAnswering(self::CART);

        $answer = $this->post(self::RUN, ['path' => '/checkout/cart', 'store' => $this->storeId()]);

        self::assertSame([['storeId' => $this->storeId(), 'path' => '/checkout/cart']], $reader->reads);
        self::assertSame('other', $this->at($answer, 'page', 'kind'));
        self::assertNull($this->at($answer, 'failure'));
        self::assertNull($this->at($answer, 'blocks', 0, 'name'), 'No snippet wrote the block.');
        self::assertSame('WebSite', $this->at($answer, 'blocks', 0, 'type'));
        self::assertSame(true, $this->at($answer, 'themeMarkup', 'found'));
        self::assertSame('Product', $this->at($answer, 'themeMarkup', 'items', 0, 'type'));
        self::assertNull(
            $this->at($answer, 'themeMarkup', 'items', 1, 'type'),
            'An item no snippet replaces, the Organization, is not offered as the theme\'s own markup.'
        );
    }

    public function testAPageOfTheShopIsTestedAtItsOwnPath(): void
    {
        $reader = $this->readerAnswering('<html><body></body></html>');

        $answer = $this->post(self::RUN, ['page' => 'product:' . $this->productId(), 'store' => $this->storeId()]);

        self::assertSame('product:' . $this->productId(), $this->at($answer, 'page', 'ref'));
        self::assertSame('Endpoint Satchel', $this->at($answer, 'page', 'label'));
        self::assertCount(1, $reader->reads);
        self::assertStringStartsWith('/', $reader->reads[0]['path']);
    }

    public function testAPageThatCannotBeReadIsAnsweredWithTheReason(): void
    {
        $this->useReader(new FixedStorefrontReader('', new StorefrontReadException(ReadFailure::HttpStatus, 503)));

        $answer = $this->post(self::RUN, ['path' => '/', 'store' => $this->storeId()]);

        self::assertSame('http_status', $this->at($answer, 'failure', 'reason'));
        self::assertSame(503, $this->at($answer, 'failure', 'httpStatus'));
        self::assertSame([], $this->at($answer, 'blocks'));
    }

    /**
     * @return array<string, array{array<string, mixed>, string}>
     */
    public static function refusedBodies(): array
    {
        return [
            'an address with a host' => [['path' => 'https://evil.example/'], 'path'],
            'a path that picks a store view' => [['path' => '/?___store=default'], 'path'],
            'a page the store view does not have' => [['page' => 'product:987654'], 'page'],
            'neither a page nor a path' => [[], 'page'],
            'both a page and a path' => [['page' => 'home', 'path' => '/'], 'page'],
        ];
    }

    /**
     * @param array<string, mixed> $body
     * @param string $reason
     */
    #[DataProvider('refusedBodies')]
    public function testARequestThatNamesNoPageOfTheStoreViewIsRefused(array $body, string $reason): void
    {
        $reader = $this->readerAnswering('<html></html>');

        $answer = $this->post(self::RUN, $body + ['store' => $this->storeId()], 400);

        self::assertSame($reason, $answer['error'] ?? null);
        self::assertSame([], $reader->reads, 'Nothing is read for a refused request.');
    }

    public function testTheTestIsAPostWithTheFormKey(): void
    {
        $this->assertPostWithoutFormKeyIsRefused(self::RUN, ['path' => '/']);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function endpoints(): array
    {
        return [
            'the page search' => [self::SEARCH, HttpRequest::METHOD_GET],
            'the page test' => [self::RUN, HttpRequest::METHOD_POST],
        ];
    }

    #[DataProvider('endpoints')]
    public function testARoleThatMayNotRunTestsIsRefused(string $path, string $method): void
    {
        $this->readerAnswering('<html></html>');
        $this->deny(AdminResources::TEST);
        $this->newExchange();
        $this->askAsTheBrowser($method, $method === HttpRequest::METHOD_POST ? ['path' => '/'] : '');

        $this->follow($path);

        self::assertSame(self::NO_ACCESS, $this->httpResponse()->getHttpResponseCode());
    }

    #[DataProvider('endpoints')]
    public function testARoleThatMayRunTestsButNotChangeSnippetsIsLetThrough(string $path, string $method): void
    {
        $this->readerAnswering('<html></html>');
        $this->deny(AdminResources::SNIPPETS);
        $this->newExchange();
        $this->askAsTheBrowser($method, $method === HttpRequest::METHOD_POST ? ['path' => '/'] : '');

        $this->follow($path);

        self::assertSame(200, $this->httpResponse()->getHttpResponseCode(), (string) $this->httpResponse()->getBody());
    }

    /**
     * Have the page test read through a reader that answers the given HTML
     *
     * @param string $html
     * @return FixedStorefrontReader
     */
    private function readerAnswering(string $html): FixedStorefrontReader
    {
        $reader = new FixedStorefrontReader($html);
        $this->useReader($reader);

        return $reader;
    }

    /**
     * Have the page test read through a reader, in place of the one that reads over HTTP
     *
     * @param FixedStorefrontReader $reader
     * @return void
     */
    private function useReader(FixedStorefrontReader $reader): void
    {
        $this->testObjectManager()->addSharedInstance($reader, HttpStorefrontReader::class);
    }

    /**
     * A list of an answer
     *
     * @param array<mixed> $answer
     * @param string $name
     * @return list<array<mixed>>
     */
    private function list(array $answer, string $name): array
    {
        $list = $answer[$name] ?? null;
        self::assertIsArray($list);
        self::assertTrue(array_is_list($list));

        return array_values(array_filter($list, 'is_array'));
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
     * The default store view's ID
     *
     * @return int
     */
    private function storeId(): int
    {
        $stores = $this->testObjectManager()->get(StoreManagerInterface::class);
        self::assertInstanceOf(StoreManagerInterface::class, $stores);

        return (int) $stores->getDefaultStoreView()?->getId();
    }
}
