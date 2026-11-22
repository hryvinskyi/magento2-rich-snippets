<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Test\Integration\Controller\Adminhtml\Overview;

use Hryvinskyi\RichSnippets\Model\Admin\AdminResources;
use Hryvinskyi\RichSnippets\Model\Cache\Type\RichSnippetsCache;
use Hryvinskyi\RichSnippets\Model\Check\Storage\CheckTables;
use Hryvinskyi\RichSnippets\Model\ResourceModel\Snippet as SnippetResource;
use Hryvinskyi\RichSnippets\Test\Integration\Fixture\AsksAsTheSnippetsPage;
use Magento\Backend\Model\Url;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\App\ResourceConnection;
use Magento\TestFramework\Fixture\AppArea;
use Magento\TestFramework\Fixture\AppIsolation;
use Magento\TestFramework\Fixture\Config;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\TestCase\AbstractBackendController;

/**
 * The Overview page, rendered by the real admin: the island root with its document — the shell with the module
 * navigation, the store views, the page's phrases, its endpoint and the screens it links to with their secret keys,
 * the form key, the admin's permissions and the first Overview — and only the tabs and links the admin may open
 *
 * The parent class adds the access tests for the module's view permission. The island's built file is not run here.
 */
#[AppArea('adminhtml'), AppIsolation(true), DbIsolation(true)]
class OverviewIndexTest extends AbstractBackendController
{
    use AsksAsTheSnippetsPage;

    private const PAGE = 'backend/richsnippets/overview/index';
    private const ISLAND = 'rich-snippets-overview';
    private const PHRASES = __DIR__ . '/../../../../_contract/admin-pages/overview-phrases.json';

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
     * Start every test with no snippet and no run, whatever the database kept
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $resource = $this->testObjectManager()->get(ResourceConnection::class);
        self::assertInstanceOf(ResourceConnection::class, $resource);
        $resource->getConnection()->delete($resource->getTableName(CheckTables::RUN));
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
    public function testThePageCarriesTheIslandWithItsDocumentAndTheFirstOverview(): void
    {
        $page = $this->openPage();

        self::assertSame('Rich Snippets', $this->at($page, 'shell', 'module'));
        self::assertSame('Overview', $this->at($page, 'shell', 'page'));
        self::assertSame(
            [['overview', true], ['snippets', false], ['library', false], ['test', false], ['settings', false]],
            array_map(
                static fn (mixed $tab): array => [is_array($tab) ? $tab['code'] ?? null : null,
                    is_array($tab) ? $tab['current'] ?? null : null],
                $this->listAt($page, 'shell', 'nav')
            )
        );
        self::assertStringContainsString(
            'section/hryvinskyi_rich_snippets',
            $this->stringAt($page, 'shell', 'nav', 4, 'url')
        );
        self::assertStringContainsString('/richsnippets/overview/data/key/', $this->stringAt($page, 'urls', 'data'));
        foreach (['hryvinskyi_richsnippets/test/index', 'hryvinskyi_richsnippets/snippets/index',
            'hryvinskyi_richsnippets/snippets/new', 'hryvinskyi_richsnippets/library/index'] as $route) {
            self::assertMatchesRegularExpression('#/key/[0-9a-f]+/#', $this->stringAt($page, 'routes', $route), $route);
        }
        self::assertNotSame('', $this->stringAt($page, 'formKey'));
        self::assertSame(['snippets' => true, 'test' => true, 'config' => true], $this->at($page, 'permissions'));
        self::assertNotSame([], $this->listAt($page, 'stores'));
        self::assertIsInt($this->at($page, 'stores', 0, 'id'));
        self::assertSame('none', $this->at($page, 'data', 'state'));
        self::assertNull($this->at($page, 'data', 'store'));
        self::assertSame('other', $this->at($page, 'data', 'kinds', 'rows', 4, 'kind'));
    }

    public function testThePageCarriesExactlyThePhrasesItsIslandAsksFor(): void
    {
        $page = $this->openPage();

        $phrases = $this->at($page, 'phrases');
        self::assertIsArray($phrases);
        $contract = json_decode((string) file_get_contents(self::PHRASES), true);
        self::assertIsArray($contract);
        self::assertSame($contract['keys'] ?? null, array_keys($phrases));
        self::assertSame('Store view', $phrases['page.store.label'] ?? null);
    }

    public function testWithoutTheRightToRunTestsTheTestTabStaysForTheFindingsAndThePageSaysSo(): void
    {
        $this->deny(AdminResources::TEST);

        $page = $this->openPage();

        self::assertSame(
            ['overview', 'snippets', 'library', 'test', 'settings'],
            array_map(
                static fn (mixed $tab): mixed => is_array($tab) ? $tab['code'] ?? null : null,
                $this->listAt($page, 'shell', 'nav')
            )
        );
        self::assertMatchesRegularExpression(
            '#/richsnippets/test/index/#',
            $this->stringAt($page, 'routes', 'hryvinskyi_richsnippets/test/index')
        );
        self::assertFalse($this->at($page, 'permissions', 'test'));
    }

    public function testWithoutTheRightToChangeSnippetsOrSettingsTheirScreensAreLeftOut(): void
    {
        $this->deny(AdminResources::SNIPPETS);
        $this->deny(AdminResources::CONFIG);

        $page = $this->openPage();

        self::assertNull($this->at($page, 'routes', 'hryvinskyi_richsnippets/snippets/new'));
        self::assertNull($this->at($page, 'routes', 'adminhtml/system_config/edit'));
        self::assertIsString($this->at($page, 'routes', 'hryvinskyi_richsnippets/library/index'));
        self::assertSame(['snippets' => false, 'test' => true, 'config' => false], $this->at($page, 'permissions'));
        self::assertNotContains(
            'settings',
            array_map(
                static fn (mixed $tab): mixed => is_array($tab) ? $tab['code'] ?? null : null,
                $this->listAt($page, 'shell', 'nav')
            )
        );
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
        self::assertStringContainsString('Hryvinskyi_RichSnippets/js/admin-pages/overview', $html);
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
     * The list at a path of names, empty when there is none
     *
     * @param mixed $document
     * @param string|int ...$path
     * @return list<mixed>
     */
    private function listAt(mixed $document, string|int ...$path): array
    {
        $value = $this->at($document, ...$path);
        self::assertIsArray($value);

        return array_values($value);
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
