<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Test\Integration\Controller\Adminhtml\Snippets;

use Hryvinskyi\RichSnippets\Model\Admin\AdminResources;
use Hryvinskyi\RichSnippets\Model\Cache\Type\RichSnippetsCache;
use Hryvinskyi\RichSnippets\Model\ResourceModel\Snippet as SnippetResource;
use Hryvinskyi\RichSnippets\Test\Integration\Fixture\AsksAsTheSnippetsPage;
use Hryvinskyi\RichSnippets\Test\Integration\Fixture\StoresTestSnippets;
use Magento\Backend\Model\Url;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\App\ResourceConnection;
use Magento\TestFramework\Fixture\AppArea;
use Magento\TestFramework\Fixture\AppIsolation;
use Magento\TestFramework\Fixture\Config;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\TestCase\AbstractBackendController;

/**
 * The Snippets page, rendered by the real admin: the island root with its document — the shell with the Snippets tab
 * current, the page's phrases, its seven endpoints and the screens it links to with their secret keys, the form key,
 * the admin's permissions, and the first list with the kinds of page it is filtered by — and only the links the
 * admin may open
 *
 * The parent class adds the access tests for the module's view permission. The island's built file is not run here.
 */
#[AppArea('adminhtml'), AppIsolation(true), DbIsolation(true)]
class SnippetsIndexTest extends AbstractBackendController
{
    use AsksAsTheSnippetsPage;
    use StoresTestSnippets;

    private const PAGE = 'backend/richsnippets/snippets/index';
    private const ISLAND = 'rich-snippets-snippets';
    private const PHRASES = __DIR__ . '/../../../../_contract/admin-pages/snippets-phrases.json';

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
     * Start every test with no snippet, whatever the database kept
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $resource = $this->testObjectManager()->get(ResourceConnection::class);
        self::assertInstanceOf(ResourceConnection::class, $resource);
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

    #[Config(Url::XML_PATH_USE_SECURE_KEY, '1')]
    public function testThePageCarriesTheIslandWithItsDocumentAndTheFirstList(): void
    {
        $this->storeSnippet('rs-list-product', 'Product', [
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => '{{product.name}}',
        ], ['kinds' => ['product'], 'active' => true]);

        $page = $this->openPage();

        self::assertSame('Snippets', $this->at($page, 'shell', 'page'));
        self::assertSame(
            [['overview', false], ['snippets', true], ['library', false], ['test', false], ['settings', false]],
            array_map(
                static fn (array $tab): array => [$tab['code'] ?? null, $tab['current'] ?? null],
                $this->listAt($page, 'shell', 'nav')
            )
        );
        foreach ([
            'list' => 'list', 'setActive' => 'setActive', 'duplicate' => 'duplicate', 'delete' => 'delete',
            'export' => 'export', 'importPreview' => 'importPreview', 'importCommit' => 'importCommit',
        ] as $name => $action) {
            self::assertMatchesRegularExpression(
                '#/richsnippets/snippets/' . $action . '/key/[0-9a-f]+/#',
                $this->stringAt($page, 'urls', $name),
                $name
            );
        }
        foreach (['hryvinskyi_richsnippets/snippets/new', 'hryvinskyi_richsnippets/snippets/edit',
            'hryvinskyi_richsnippets/library/index'] as $route) {
            self::assertMatchesRegularExpression('#/key/[0-9a-f]+/#', $this->stringAt($page, 'routes', $route), $route);
        }
        self::assertNotSame('', $this->stringAt($page, 'formKey'));
        self::assertSame(['snippets' => true, 'test' => true, 'config' => true], $this->at($page, 'permissions'));
        self::assertSame(
            ['home', 'product', 'category', 'cms', 'other'],
            array_column($this->listAt($page, 'data', 'kinds'), 'code')
        );
        self::assertSame(
            ['code' => 'product', 'label' => 'Product pages', 'phrase' => 'product pages', 'icon' => 'inventory_2'],
            $this->at($page, 'data', 'kinds', 1)
        );
        self::assertStringStartsWith('Each snippet that is on writes one JSON-LD script block', $this->stringAt(
            $page,
            'data',
            'outputLine'
        ));
        self::assertSame(['rs-list-product'], array_column($this->listAt($page, 'data', 'list', 'rows'), 'code'));
        self::assertSame(1, $this->at($page, 'data', 'list', 'counts', 'on'));
    }

    public function testTheSnippetsTabCountsTheSnippetsThatNeedAttentionAndTheOtherTabsNothing(): void
    {
        $this->storeSnippet('rs-tab-product', 'Product', [
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => '{{product.name}}',
        ], ['kinds' => ['product'], 'active' => true]);

        $page = $this->openPage();

        $attention = $this->at($page, 'data', 'list', 'counts', 'attention');
        self::assertIsInt($attention);
        self::assertGreaterThan(0, $attention);
        self::assertSame(
            ['overview' => 0, 'snippets' => $attention, 'library' => 0, 'test' => 0, 'settings' => 0],
            array_column($this->listAt($page, 'shell', 'nav'), 'count', 'code')
        );
    }

    public function testWithNoSnippetTheSnippetsTabCountsNothing(): void
    {
        $page = $this->openPage();

        self::assertSame(
            [0, 0, 0, 0, 0],
            array_column($this->listAt($page, 'shell', 'nav'), 'count')
        );
    }

    public function testThePageCarriesExactlyThePhrasesItsIslandAsksFor(): void
    {
        $page = $this->openPage();

        $phrases = $this->at($page, 'phrases');
        self::assertIsArray($phrases);
        $contract = json_decode((string) file_get_contents(self::PHRASES), true);
        self::assertIsArray($contract);
        self::assertSame($contract['keys'] ?? null, array_keys($phrases));
        self::assertSame('On', $phrases['snippets.switch.on'] ?? null);
    }

    public function testWithoutTheRightToChangeSnippetsTheNewSnippetIsLeftOutAndThePageSaysSo(): void
    {
        $this->deny(AdminResources::SNIPPETS);

        $page = $this->openPage();

        self::assertNull($this->at($page, 'routes', 'hryvinskyi_richsnippets/snippets/new'));
        self::assertIsString($this->at($page, 'routes', 'hryvinskyi_richsnippets/snippets/edit'));
        self::assertIsString($this->at($page, 'routes', 'hryvinskyi_richsnippets/library/index'));
        self::assertFalse($this->at($page, 'permissions', 'snippets'));
    }

    /**
     * Open the page as an admin does and read its island's document
     *
     * @return array<mixed> The page's part of the document
     */
    private function openPage(): array
    {
        $this->newExchange();
        $this->httpRequest()->setMethod(HttpRequest::METHOD_GET);
        $this->follow(self::PAGE);

        $html = (string) $this->httpResponse()->getBody();
        self::assertSame(200, $this->httpResponse()->getHttpResponseCode());
        $xpath = $this->parse($html);
        $root = $this->element($xpath, '//div[@data-role="' . self::ISLAND . '"]');
        self::assertStringContainsString('rich-snippets-page', $root->getAttribute('class'));
        self::assertNotSame('', $root->getAttribute('data-failed-text'));
        self::assertStringContainsString('Hryvinskyi_RichSnippets/js/admin-pages/snippets', $html);
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
