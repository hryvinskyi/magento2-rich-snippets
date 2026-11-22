<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Test\Integration\Controller\Adminhtml\Library;

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
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The Library page, rendered by the real admin: the island root with its document — the shell with the Library tab
 * current, the page's phrases, its four endpoints and the screens it links to with their secret keys, the form key,
 * the admin's permission, and the library as its list endpoint answers it with the template the address names — and
 * only the links the admin may open
 *
 * The parent class adds the access tests for the module's view permission. The island's built file is not run here.
 */
#[AppArea('adminhtml'), AppIsolation(true), DbIsolation(true)]
class LibraryIndexTest extends AbstractBackendController
{
    use AsksAsTheSnippetsPage;
    use StoresTestSnippets;

    private const PAGE = 'backend/richsnippets/library/index';
    private const ISLAND = 'rich-snippets-library';
    private const CONTRACTS = __DIR__ . '/../../../../_contract/admin-pages/';
    private const TEMPLATES = [
        'organization', 'website-name', 'breadcrumbs', 'local-business', 'product', 'product-variants',
        'product-list', 'category', 'faq', 'article', 'return-policy', 'shipping-policy', 'postal-address',
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
    public function testThePageCarriesTheIslandWithItsDocumentAndTheLibrary(): void
    {
        $this->storeSnippet('faq', 'FAQ', ['@context' => 'https://schema.org', '@type' => 'FAQPage']);

        $page = $this->openPage();

        self::assertSame('Library', $this->at($page, 'shell', 'page'));
        self::assertSame(
            [['overview', false], ['snippets', false], ['library', true], ['test', false], ['settings', false]],
            array_map(
                static fn (array $tab): array => [$tab['code'] ?? null, $tab['current'] ?? null],
                $this->listAt($page, 'shell', 'nav')
            )
        );
        foreach (['list', 'suggestions', 'add', 'addRecommended'] as $name) {
            self::assertMatchesRegularExpression(
                '#/richsnippets/library/' . $name . '/key/[0-9a-f]+/#',
                $this->stringAt($page, 'urls', $name),
                $name
            );
        }
        foreach (['hryvinskyi_richsnippets/snippets/new', 'hryvinskyi_richsnippets/snippets/edit',
            'hryvinskyi_richsnippets/test/index'] as $route) {
            self::assertMatchesRegularExpression('#/key/[0-9a-f]+/#', $this->stringAt($page, 'routes', $route), $route);
        }
        self::assertNotSame('', $this->stringAt($page, 'formKey'));
        self::assertTrue($this->at($page, 'permissions', 'snippets'));
        $data = $this->at($page, 'data');
        self::assertIsArray($data);
        self::assertSame(self::TEMPLATES, array_column($this->listAt($page, 'data', 'templates'), 'code'));
        self::assertSame(['shop', 'product', 'category', 'content', 'blocks'], array_column(
            $this->listAt($page, 'data', 'groups'),
            'code'
        ));
        self::assertSame(
            ['organization', 'website-name', 'breadcrumbs', 'product', 'product-variants'],
            $this->at($page, 'data', 'recommended')
        );
        self::assertFalse($this->at($page, 'data', 'templates', 8, 'added'), 'a snippet not added from the library');
        self::assertSame('FAQ 2', $this->at($page, 'data', 'templates', 8, 'nextName'), 'its name is taken');
        self::assertNull($this->at($page, 'data', 'open'));
        self::assertFalse($this->at($page, 'data', 'unavailable'));
        $library = $data;
        unset($library['open'], $library['unavailable']);
        $this->assertSameShape($this->contract('library-list.json')['lib'] ?? null, $library);
    }

    /**
     * @return array<string, array{0: string, 1: string|null}>
     */
    public static function addresses(): array
    {
        return [
            'a template the library has' => ['product', 'product'],
            'a template it does not have' => ['recipe', null],
        ];
    }

    #[DataProvider('addresses')]
    public function testThePageOpensTheDrawerOfATemplateTheLibraryHasWhenTheAddressNamesIt(
        string $asked,
        ?string $open
    ): void {
        self::assertSame($open, $this->at($this->openPage(['template' => $asked]), 'data', 'open'));
    }

    public function testThePageCarriesExactlyThePhrasesItsIslandAsksFor(): void
    {
        $page = $this->openPage();

        $phrases = $this->at($page, 'phrases');
        self::assertIsArray($phrases);
        self::assertSame($this->contract('library-phrases.json')['keys'] ?? null, array_keys($phrases));
        self::assertSame('Added', $phrases['library.card.added'] ?? null);
    }

    public function testWithoutTheRightToChangeSnippetsTheNewSnippetIsLeftOutAndThePageSaysSo(): void
    {
        $this->deny(AdminResources::SNIPPETS);

        $page = $this->openPage();

        self::assertNull($this->at($page, 'routes', 'hryvinskyi_richsnippets/snippets/new'));
        self::assertIsString($this->at($page, 'routes', 'hryvinskyi_richsnippets/snippets/edit'));
        self::assertFalse($this->at($page, 'permissions', 'snippets'));
        self::assertCount(13, $this->listAt($page, 'data', 'templates'));
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
            'The library could not be loaded. Reload the page to try again.',
            $root->getAttribute('data-failed-text')
        );
        self::assertStringContainsString('Hryvinskyi_RichSnippets/js/admin-pages/library', $html);
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
     * Whether an answer has the keys of the contract's example at every level, lists compared by their first item
     *
     * @param mixed $contract
     * @param mixed $answer
     * @param string $path
     * @return void
     */
    private function assertSameShape(mixed $contract, mixed $answer, string $path = '$'): void
    {
        if (!is_array($contract) || $contract === []) {
            return;
        }
        self::assertIsArray($answer, $path);
        if (array_is_list($contract)) {
            if ($answer !== []) {
                self::assertTrue(array_is_list($answer), $path . ' is a list.');
                $this->assertSameShape($contract[0], $answer[0], $path . '[0]');
            }

            return;
        }
        self::assertSame(array_keys($contract), array_keys($answer), $path);
        foreach ($contract as $key => $value) {
            $this->assertSameShape($value, $answer[$key], $path . '.' . $key);
        }
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
