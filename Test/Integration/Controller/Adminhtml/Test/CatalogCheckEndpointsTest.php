<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Test\Integration\Controller\Adminhtml\Test;

use Hryvinskyi\RichSnippets\Api\Data\CheckTrigger;
use Hryvinskyi\RichSnippets\Model\Admin\AdminResources;
use Hryvinskyi\RichSnippets\Model\Cache\Type\RichSnippetsCache;
use Hryvinskyi\RichSnippets\Model\Check\CheckRunStoreInterface;
use Hryvinskyi\RichSnippets\Model\Check\RequestedChecks;
use Hryvinskyi\RichSnippets\Model\Check\Storage\CheckTables;
use Hryvinskyi\RichSnippets\Test\Integration\Fixture\AsksAsTheSnippetsPage;
use Hryvinskyi\RichSnippets\Test\Integration\Fixture\StoresTestSnippets;
use Laminas\Http\Header\HeaderInterface;
use Magento\Catalog\Test\Fixture\Virtual as VirtualProductFixture;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Model\AbstractModel;
use Magento\TestFramework\Fixture\AppArea;
use Magento\TestFramework\Fixture\AppIsolation;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Fixture\DataFixtureStorageManager;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\TestCase\AbstractBackendController;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The Test page's whole-catalog endpoints: where the check stands, asking for one, the findings of the last check,
 * the pages behind a finding and their download; a request that names no finding or no part of the list is refused,
 * the request for a check is a POST with the form key, and each endpoint answers only the roles it is meant for
 *
 * The product snippet is placed on virtual products only, so the fixture's page is the one the check finds a gap on.
 * Its name starts with an equals sign, which a spreadsheet would run as a formula.
 */
#[
    AppArea('adminhtml'),
    AppIsolation(true),
    DbIsolation(true),
    DataFixture(
        VirtualProductFixture::class,
        ['sku' => 'rs-endpoint-formula', 'name' => '=HYPERLINK("https://example.com")'],
        as: 'product'
    ),
]
class CatalogCheckEndpointsTest extends AbstractBackendController
{
    use AsksAsTheSnippetsPage;
    use StoresTestSnippets;

    private const STATUS = 'backend/richsnippets/test/checkStatus';
    private const REQUEST = 'backend/richsnippets/test/checkRequest';
    private const FINDINGS = 'backend/richsnippets/test/findings';
    private const ITEMS = 'backend/richsnippets/test/findingItems';
    private const CSV = 'backend/richsnippets/test/findingsCsv';
    private const NO_ACCESS = 403;

    /**
     * @var string
     */
    protected $resource = AdminResources::VIEW;

    /**
     * @var string
     */
    protected $uri = self::STATUS;

    /**
     * @var string
     */
    protected $httpMethod = HttpRequest::METHOD_GET;

    /**
     * Start every test with no runs, whatever the database kept
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $resource = $this->testObjectManager()->get(ResourceConnection::class);
        self::assertInstanceOf(ResourceConnection::class, $resource);
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

    public function testTheStatusSaysNoCheckHasRunAndAskingStartsOne(): void
    {
        $before = $this->read(self::STATUS);
        $asked = $this->post(self::REQUEST, []);
        $again = $this->post(self::REQUEST, []);

        self::assertSame(['idle', 'none'], [$before['state'] ?? null, $before['tone'] ?? null]);
        self::assertSame('requested', $asked['state'] ?? null);
        self::assertSame('requested', $again['state'] ?? null);
        $runs = $this->testObjectManager()->get(CheckRunStoreInterface::class);
        self::assertInstanceOf(CheckRunStoreInterface::class, $runs);
        self::assertSame(CheckTrigger::Manual, $runs->pending()?->getTrigger());
    }

    public function testTheFindingsOfTheLastCheckAndThePagesBehindOneAreAnswered(): void
    {
        $this->checkTheCatalog();

        $status = $this->read(self::STATUS);
        $findings = $this->read(self::FINDINGS);
        $row = $this->row($findings, 'No image');
        $items = $this->read(self::ITEMS, ['key' => $this->keyOf($row)]);

        self::assertSame(['idle', 'invalid', '1 product is invalid'], [
            $status['state'] ?? null, $status['tone'] ?? null, $status['title'] ?? null,
        ]);
        self::assertArrayHasKey('store', $findings);
        self::assertNull($findings['store'], 'Every store view.');
        self::assertSame(['bad', 1, '1 product'], [$row['tone'] ?? null, $row['pages'] ?? null, $row['count'] ?? null]);
        self::assertSame(1, $items['total'] ?? null);
        self::assertSame('=HYPERLINK("https://example.com")', $this->at($items, 'items', 0, 'label'));
        self::assertSame('rs-endpoint-formula', $this->at($items, 'items', 0, 'sku'));
        self::assertSame($this->productId(), $this->at($items, 'items', 0, 'id'));
    }

    public function testTheDownloadListsThePagesWithFormulaCellsDefused(): void
    {
        $this->checkTheCatalog();
        $key = $this->keyOf($this->row($this->read(self::FINDINGS), 'No image'));

        $this->newExchange();
        $request = $this->httpRequest();
        $request->setMethod(HttpRequest::METHOD_GET);
        $request->setQueryValue('key', $key);
        $this->follow(self::CSV);

        $response = $this->httpResponse();
        self::assertSame(200, $response->getHttpResponseCode(), (string) $response->getBody());
        self::assertStringStartsWith('text/csv', $this->header('Content-Type'));
        self::assertStringContainsString(
            'attachment; filename="rich-snippets-' . $key . '.csv"',
            $this->header('Content-Disposition')
        );
        $lines = explode("\n", trim((string) $response->getBody()));
        self::assertCount(2, $lines);
        self::assertStringContainsString('"\'=HYPERLINK(""https://example.com"")"', $lines[1]);
        self::assertStringContainsString('rs-endpoint-formula', $lines[1]);
    }

    /**
     * @return array<string, array{string, array<string, string>, string}>
     */
    public static function refusedQueries(): array
    {
        return [
            'a finding the last check did not find' => [self::ITEMS, ['key' => 'merchant-listing-nothing'], 'key'],
            'no finding at all' => [self::ITEMS, [], 'key'],
            'a key with a slash' => [self::ITEMS, ['key' => '../etc'], 'key'],
            'a part of the list before the first' => [self::ITEMS, ['key' => 'merchant-listing-image', 'page' => '0'],
                'page'],
            'a download of nothing found' => [self::CSV, ['key' => 'merchant-listing-nothing'], 'key'],
        ];
    }

    /**
     * @param string $path
     * @param array<string, string> $query
     * @param string $reason
     */
    #[DataProvider('refusedQueries')]
    public function testARequestThatNamesNoFindingIsRefused(string $path, array $query, string $reason): void
    {
        $this->checkTheCatalog();

        $answer = $this->read($path, $query, 400);

        self::assertSame($reason, $answer['error'] ?? null);
    }

    public function testAskingForACheckIsAPostWithTheFormKey(): void
    {
        $this->assertPostWithoutFormKeyIsRefused(self::REQUEST, []);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function readEndpoints(): array
    {
        return [
            'the status' => [self::STATUS],
            'the findings' => [self::FINDINGS],
            'the pages of a finding' => [self::ITEMS],
            'the download' => [self::CSV],
        ];
    }

    #[DataProvider('readEndpoints')]
    public function testARoleThatMayNotSeeTheSnippetsIsRefusedTheFindings(string $path): void
    {
        $this->deny(AdminResources::VIEW);
        $this->newExchange();
        $this->askAsTheBrowser(HttpRequest::METHOD_GET);

        $this->follow($path);

        self::assertSame(self::NO_ACCESS, $this->httpResponse()->getHttpResponseCode());
    }

    public function testARoleThatMayNotRunTestsCannotAskForACheck(): void
    {
        $this->deny(AdminResources::TEST);
        $this->newExchange();
        $this->askAsTheBrowser(HttpRequest::METHOD_POST, []);

        $this->follow(self::REQUEST);

        self::assertSame(self::NO_ACCESS, $this->httpResponse()->getHttpResponseCode());
    }

    public function testARoleThatMayRunTestsButNotChangeSnippetsMayAskForACheck(): void
    {
        $this->deny(AdminResources::SNIPPETS);

        $answer = $this->post(self::REQUEST, []);

        self::assertSame('requested', $answer['state'] ?? null);
    }

    /**
     * Store the product snippet for virtual products, ask for a check and run it as the minute job does
     *
     * @return void
     */
    private function checkTheCatalog(): void
    {
        $this->storeSnippet('rs-endpoint-product', 'Endpoint product', [
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
     * The row of a findings answer with a title
     *
     * @param array<mixed> $answer
     * @param string $title
     * @return array<mixed>
     */
    private function row(array $answer, string $title): array
    {
        $rows = $answer['findings'] ?? null;
        self::assertIsArray($rows);
        foreach ($rows as $row) {
            if (is_array($row) && ($row['title'] ?? null) === $title) {
                return $row;
            }
        }
        self::fail(sprintf('No finding is titled "%s": %s', $title, json_encode($rows)));
    }

    /**
     * The key of a findings row
     *
     * @param array<mixed> $row
     * @return string
     */
    private function keyOf(array $row): string
    {
        $key = $row['key'] ?? null;
        self::assertIsString($key);

        return $key;
    }

    /**
     * A header of the last response
     *
     * @param string $name
     * @return string
     */
    private function header(string $name): string
    {
        $header = $this->httpResponse()->getHeader($name);
        self::assertInstanceOf(HeaderInterface::class, $header, $name . ' is sent.');

        return $header->getFieldValue();
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
