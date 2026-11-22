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
use Hryvinskyi\RichSnippets\Test\Integration\Fixture\ReadsTheEditorPage;
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
 * The editor of a stored snippet, rendered by the real admin: the island root with the expression builder's
 * stylesheet and its document — the shell with the Snippets tab current, exactly the phrases the island asks for,
 * the editor's endpoints and the screens it links to with their secret keys, and the snippet with the snippets that
 * include it, the condition fields, the kinds of page, the suggested types and the values a property starts with
 *
 * The parent class adds the access tests for the module's view permission. An address naming no stored snippet
 * answers page-not-found. The island's built file is not run here.
 */
#[AppArea('adminhtml'), AppIsolation(true), DbIsolation(true)]
class EditorPageTest extends AbstractBackendController
{
    use AsksAsTheSnippetsPage;
    use ReadsTheEditorPage;
    use StoresTestSnippets;

    /**
     * @var string
     */
    protected $resource = AdminResources::VIEW;

    /**
     * @var string
     */
    protected $httpMethod = HttpRequest::METHOD_GET;

    /**
     * @var int
     */
    private int $policyId = 0;

    /**
     * Start every test with a building block and a snippet that uses it, whatever the database kept
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
        $policy = $this->storeSnippet('rs-editor-policy', 'Return policy', [
            '@type' => 'MerchantReturnPolicy',
            'merchantReturnDays' => 30,
        ], ['block' => true]);
        $this->policyId = (int) $policy->getId();
        $this->uri = 'backend/richsnippets/snippets/edit/id/' . $this->policyId;
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
    public function testThePageCarriesTheSnippetWithWhatTheEditorDrawsAroundIt(): void
    {
        $product = $this->storeSnippet('rs-editor-product', 'Product', [
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => '{{product.name}}',
            'offers' => ['@type' => 'Offer', 'hasMerchantReturnPolicy' => ['@include' => 'rs-editor-policy']],
        ], ['kinds' => ['product'], 'active' => true]);

        $page = $this->openEditor('backend/richsnippets/snippets/edit/id/' . $this->policyId);

        self::assertSame('Snippets', $this->at($page, 'shell', 'page'));
        self::assertSame(
            ['overview' => false, 'snippets' => true, 'library' => false, 'test' => false, 'settings' => false],
            $this->currentTabs($page)
        );
        foreach ([
            'load', 'save', 'validate', 'preview', 'fields', 'samples', 'includeCandidates', 'builderConfig',
            'placementCount',
        ] as $name) {
            self::assertMatchesRegularExpression(
                '#/richsnippets/snippets/' . $name . '/key/[0-9a-f]+/#',
                $this->textAt($page, 'urls', $name),
                $name
            );
        }
        foreach (['hryvinskyi_richsnippets/snippets/index', 'hryvinskyi_richsnippets/snippets/edit',
            'hryvinskyi_richsnippets/library/index', 'hryvinskyi_richsnippets/test/index'] as $route) {
            self::assertMatchesRegularExpression('#/key/[0-9a-f]+/#', $this->textAt($page, 'routes', $route), $route);
        }
        self::assertSame(['snippets' => true, 'test' => true, 'config' => true], $this->at($page, 'permissions'));
        self::assertSame($this->policyId, $this->at($page, 'data', 'snippet', 'id'));
        self::assertSame('rs-editor-policy', $this->at($page, 'data', 'snippet', 'code'));
        self::assertTrue($this->at($page, 'data', 'snippet', 'isBlock'));
        self::assertStringContainsString(
            '"merchantReturnDays":30',
            $this->textAt($page, 'data', 'snippet', 'template')
        );
        self::assertFalse($this->at($page, 'data', 'isNew'));
        self::assertSame('properties', $this->at($page, 'data', 'section'));
        self::assertSame(
            [['id' => $product->getId(), 'code' => 'rs-editor-product', 'name' => 'Product',
                'paths' => ['offers.hasMerchantReturnPolicy']]],
            $this->at($page, 'data', 'usedIn')
        );
        self::assertContains(
            ['code' => 'product_type', 'label' => 'Product type'],
            $this->listAt($page, 'data', 'vocabulary', 'conditions', 'product')
        );
        self::assertSame(
            [
                'code' => 'cms',
                'label' => 'CMS pages',
                'phrase' => 'CMS pages',
                'page' => 'CMS page',
                'icon' => 'article',
            ],
            $this->at($page, 'data', 'kinds', 3)
        );
        self::assertSame(
            ['Product', 'OnlineStore', 'Store', 'BreadcrumbList', 'Article', 'VideoObject', 'Event'],
            $this->at($page, 'data', 'typeSuggestions')
        );
        self::assertSame([
            'text' => '{{product.name}} by {{store.name}}',
            'code' => '{"@if": "product.on_sale", "@value": "{{product.special_to_date | date}}"}',
            'when' => 'product.on_sale',
            'list' => ['each' => 'product.gallery', 'item' => 'image.url'],
        ], $this->at($page, 'data', 'defaults'));
    }

    public function testAnAddressAskingForThePagesOpensTheEditorOnThem(): void
    {
        $page = $this->openEditor('backend/richsnippets/snippets/edit/id/' . $this->policyId . '/section/pages');

        self::assertSame('pages', $this->at($page, 'data', 'section'));
    }

    public function testThePageCarriesExactlyThePhrasesItsIslandAsksFor(): void
    {
        $page = $this->openEditor('backend/richsnippets/snippets/edit/id/' . $this->policyId);

        $phrases = $this->at($page, 'phrases');
        self::assertIsArray($phrases);
        self::assertSame($this->contractKeys(), array_keys($phrases));
        self::assertSame('Unsaved changes', $phrases['editor.header.unsaved'] ?? null);
    }

    public function testAnAddressWithoutAStoredSnippetAnswersPageNotFound(): void
    {
        foreach (['backend/richsnippets/snippets/edit/id/987654', 'backend/richsnippets/snippets/edit',
            'backend/richsnippets/snippets/edit/id/x'] as $path) {
            $this->newExchange();
            $this->httpRequest()->setMethod(HttpRequest::METHOD_GET);
            $this->follow($path);

            self::assertSame(404, $this->httpResponse()->getHttpResponseCode(), $path);
            self::assertStringNotContainsString('rich-snippets-editor', (string) $this->httpResponse()->getBody());
        }
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
