<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Test\Integration\Controller;

use Hryvinskyi\HeadTagManager\Api\HeadTagManagerInterface;
use Hryvinskyi\RichSnippets\Api\ConditionTreeParserInterface;
use Hryvinskyi\RichSnippets\Api\PageEntityFactoryInterface;
use Hryvinskyi\RichSnippets\Api\PageTestInterface;
use Hryvinskyi\RichSnippets\Api\PlacementVocabularyInterface;
use Hryvinskyi\RichSnippets\Api\ThemeMarkupObservationsInterface;
use Hryvinskyi\RichSnippets\Model\Admin\Test\PageTestPresenter;
use Hryvinskyi\RichSnippets\Model\Cache\Type\RichSnippetsCache;
use Hryvinskyi\RichSnippets\Model\ResourceModel\Snippet as SnippetResource;
use Hryvinskyi\RichSnippets\Model\Snippet\SnippetFactoryInterface;
use Hryvinskyi\RichSnippets\Model\Snippet\SnippetRootRepositoryInterface;
use Hryvinskyi\RichSnippets\Model\Snippet\TemplateTypeInterface;
use Hryvinskyi\RichSnippets\Model\PageTest\PageTest;
use Hryvinskyi\RichSnippets\Model\Storefront\SnippetOutput;
use Hryvinskyi\RichSnippets\Test\Integration\Fixture\FixedStorefrontReader;
use Magento\Catalog\Test\Fixture\Category as CategoryFixture;
use Magento\Catalog\Test\Fixture\Product as ProductFixture;
use Magento\Cms\Test\Fixture\Page as PageFixture;
use Magento\Framework\App\Cache\StateInterface;
use Magento\Framework\App\PageCache\Kernel;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\App\Response\Http as HttpResponse;
use Magento\Framework\Model\AbstractModel;
use Magento\PageCache\Model\Cache\Type as PageCache;
use Magento\Store\Model\StoreManagerInterface;
use Magento\TestFramework\Fixture\AppArea;
use Magento\TestFramework\Fixture\Config;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Fixture\DataFixtureStorageManager;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\TestCase\AbstractController;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Storefront pages write their snippets as JSON-LD script blocks, where and how the settings say, and never let
 * admin text or a failing snippet break the page
 *
 * Each page is dispatched as a visitor's request. The tests that read page cache tags or the stored page cache
 * entry switch the built-in page cache on for themselves and empty it first.
 */
#[
    AppArea('frontend'),
    DbIsolation(true),
    DataFixture(
        ProductFixture::class,
        [
            'sku' => 'rs-storefront-satchel',
            'name' => 'Field Satchel',
            'description' => StorefrontSnippetsTest::DESCRIPTION,
        ],
        as: 'product'
    ),
    DataFixture(CategoryFixture::class, ['name' => 'Rich Snippet Bags'], as: 'category'),
    DataFixture(PageFixture::class, ['identifier' => 'rs-storefront-about'], as: 'page'),
]
class StorefrontSnippetsTest extends AbstractController
{
    private const EVERYWHERE = '{"@type": "Thing", "name": "{{ context.kind }}"}';
    private const PRODUCT = '{"@context": "https://schema.org", "@type": "Product", "name": "Field Satchel"}';
    private const ALL_KINDS = ['home', 'product', 'category', 'cms', 'other'];
    private const BREADCRUMBS = '{"@context": "https://schema.org", "@type": "BreadcrumbList", "itemListElement": '
        . '[{"@type": "ListItem", "position": 1, "name": "Bags", "item": "https://shop.test/bags.html"}]}';

    /**
     * A product description carrying microdata items of its own, inside the theme's Product item
     */
    public const DESCRIPTION = '<p>A roomy satchel.</p>'
        . '<div class="rs-maker" itemscope itemtype="https://schema.org/Organization">'
        . '<span itemprop="name">Rich Snippet Makers</span></div>'
        . '<ol class="rs-trail" itemscope itemtype="https://schema.org/BreadcrumbList">'
        . '<li itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem">'
        . '<span itemprop="name">Bags</span><meta itemprop="position" content="1"></li></ol>';
    private const ORGANIZATION_ITEM = '<div class="rs-maker" itemscope itemtype="https://schema.org/Organization">'
        . '<span itemprop="name">Rich Snippet Makers</span></div>';
    private const BREADCRUMB_ITEM = '<ol class="rs-trail" itemscope itemtype="https://schema.org/BreadcrumbList">'
        . '<li itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem">'
        . '<span itemprop="name">Bags</span><meta itemprop="position" content="1"></li></ol>';
    private const BREADCRUMB_WITHOUT_MICRODATA = '<ol class="rs-trail"><li><span>Bags</span>'
        . '<meta content="1"></li></ol>';

    /**
     * @var bool|null whether the page cache was enabled before a test switched it on
     */
    private ?bool $pageCacheWasEnabled = null;

    /**
     * Start every test with no kept selection and no head element left by an earlier request
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->get(RichSnippetsCache::class)->clean();
        $this->get(HeadTagManagerInterface::class)->clear();
        $this->get(SnippetOutput::class)->_resetState();
    }

    /**
     * Put the page cache back as it was
     *
     * @return void
     */
    protected function tearDown(): void
    {
        if ($this->pageCacheWasEnabled !== null) {
            $this->get(PageCache::class)->clean();
            $this->get(StateInterface::class)
                ->setEnabled(PageCache::TYPE_IDENTIFIER, $this->pageCacheWasEnabled);
        }
        $this->get(RichSnippetsCache::class)->clean();
        parent::tearDown();
    }

    /**
     * @return array<string, array{string}>
     */
    public static function kindsOfPage(): array
    {
        return array_combine(self::ALL_KINDS, array_map(static fn (string $kind): array => [$kind], self::ALL_KINDS));
    }

    #[DataProvider('kindsOfPage')]
    public function testEveryKindOfPageWritesItsSnippetsInTheHead(string $kind): void
    {
        $this->store('rs-test-everywhere', 'Everywhere', self::EVERYWHERE, self::ALL_KINDS);

        $html = $this->page($kind);

        self::assertMatchesRegularExpression(
            '~<head\b[^>]*>.*<script type="application/ld\+json" data-snippet="rs-test-everywhere"[^>]*>'
            . preg_quote('{"@type":"Thing","name":"' . $kind . '"}', '~') . '</script>.*</head>~s',
            $html
        );
        self::assertSame(1, substr_count($html, 'data-snippet="rs-test-everywhere"'));
    }

    #[Config('hryvinskyi_rich_snippets/output/position', 'body_end', 'store', 'default')]
    public function testTheEndOfTheBodyGetsTheBlocksWhenTheSettingSaysSo(): void
    {
        $this->store('rs-test-everywhere', 'Everywhere', self::EVERYWHERE, self::ALL_KINDS);

        [$head, $body] = $this->headAndBody($this->page('category'));

        self::assertStringNotContainsString('data-snippet="rs-test-everywhere"', $head);
        self::assertMatchesRegularExpression(
            '~<script type="application/ld\+json" data-snippet="rs-test-everywhere"[^>]*>'
            . preg_quote('{"@type":"Thing","name":"category"}', '~') . '</script>.*</body>~s',
            $body
        );
    }

    #[Config('hryvinskyi_rich_snippets/output/format', 'readable', 'store', 'default')]
    public function testReadableFormatIndentsTheJson(): void
    {
        $this->store('rs-test-everywhere', 'Everywhere', self::EVERYWHERE, self::ALL_KINDS);

        self::assertStringContainsString(
            "data-snippet=\"rs-test-everywhere\">{\n    \"@type\": \"Thing\",\n    \"name\": \"product\"\n}</script>",
            $this->page('product')
        );
    }

    #[Config('hryvinskyi_rich_snippets/general/enabled', '0', 'store', 'default')]
    public function testAStoreViewWithTheSnippetsSwitchedOffWritesNone(): void
    {
        $this->store('rs-test-everywhere', 'Everywhere', self::EVERYWHERE, self::ALL_KINDS);

        self::assertStringNotContainsString('data-snippet=', $this->page('product'));
    }

    #[
        Config('hryvinskyi_rich_snippets/developer/comments', '1', 'store', 'default'),
        Config('hryvinskyi_rich_snippets/output/position', 'body_end', 'store', 'default'),
    ]
    public function testAMarkedBlockCarriesItsCodeAndNeverItsName(): void
    {
        $this->store('rs-test-xss', 'x--><script>alert(1)</script>', self::EVERYWHERE, self::ALL_KINDS);

        $html = $this->page('product');

        self::assertStringNotContainsString('<script>alert', $html);
        self::assertStringContainsString(
            "<!-- rich-snippet: rs-test-xss -->\n<script type=\"application/ld+json\" data-snippet=\"rs-test-xss\"",
            $html
        );
    }

    #[Config('hryvinskyi_rich_snippets/developer/comments', '1', 'store', 'default')]
    public function testANameThatClosesACommentNeverReachesTheHead(): void
    {
        $this->store('rs-test-xss', 'x--><script>alert(1)</script>', self::EVERYWHERE, self::ALL_KINDS);

        $html = $this->page('product');

        self::assertStringNotContainsString('<script>alert', $html);
        self::assertStringContainsString('data-snippet="rs-test-xss"', $html);
        [$head] = $this->headAndBody($html);
        self::assertStringContainsString(
            "<!-- rich-snippet: rs-test-xss -->\n<script type=\"application/ld+json\" data-snippet=\"rs-test-xss\"",
            $head
        );
    }

    #[Config('hryvinskyi_rich_snippets/developer/comments', '1', 'store', 'default')]
    public function testAMarkedBlockInTheHeadIsPrecededByTheCommentNamingItsCode(): void
    {
        $this->store('rs-test-everywhere', 'Everywhere', self::EVERYWHERE, self::ALL_KINDS);

        [$head, $body] = $this->headAndBody($this->page('category'));

        self::assertMatchesRegularExpression(
            '~<!-- rich-snippet: rs-test-everywhere -->\n<script type="application/ld\+json"'
            . ' data-snippet="rs-test-everywhere"[^>]*>' . preg_quote('{"@type":"Thing","name":"category"}', '~')
            . '</script>~',
            $head
        );
        self::assertSame(1, substr_count($head, '<!-- rich-snippet: rs-test-everywhere -->'));
        self::assertStringNotContainsString('rich-snippet:', $body);
    }

    public function testAnUnmarkedBlockInTheHeadHasNoComment(): void
    {
        $this->store('rs-test-everywhere', 'Everywhere', self::EVERYWHERE, self::ALL_KINDS);

        $html = $this->page('category');

        self::assertStringContainsString('data-snippet="rs-test-everywhere"', $html);
        self::assertStringNotContainsString('rich-snippet:', $html);
    }

    public function testAFailingSnippetDoesNotStopTheOthers(): void
    {
        $this->store('rs-test-broken', 'Broken', self::EVERYWHERE, self::ALL_KINDS);
        $this->store('rs-test-everywhere', 'Everywhere', self::EVERYWHERE, self::ALL_KINDS);
        $this->breakTemplate('rs-test-broken');

        $html = $this->page('product');

        self::assertStringNotContainsString('data-snippet="rs-test-broken"', $html);
        self::assertStringContainsString('data-snippet="rs-test-everywhere"', $html);
    }

    public function testAPageWithNoSnippetCarriesTheTagsOfItsKindAndStoreView(): void
    {
        $this->enablePageCache();

        $this->page('category');

        $tags = $this->tagsHeader();
        $storeId = $this->storeId();
        self::assertContains('rich_snippets_kind_category_' . $storeId, $tags);
        self::assertContains('rich_snippets_store_' . $storeId, $tags);
    }

    public function testTheStoredProductPageLosesOnlyTheThemesProductItemWhenAProductSnippetIsWritten(): void
    {
        $this->enablePageCache();
        $this->store('rs-test-product', 'Product', self::PRODUCT, ['product']);

        $html = $this->page('product');
        $stored = $this->storedPage();

        self::assertStringContainsString('data-snippet="rs-test-product"', $stored);
        foreach ([$html, $stored] as $copy) {
            $this->assertNoProductMicrodata($copy);
            self::assertStringContainsString(self::ORGANIZATION_ITEM, $copy, 'An item no snippet writes stays.');
            self::assertStringContainsString(self::BREADCRUMB_ITEM, $copy, 'An item no snippet writes stays.');
        }
    }

    public function testTheStoredProductPageLosesTheThemesBreadcrumbsWhenABreadcrumbSnippetIsWrittenToo(): void
    {
        $this->enablePageCache();
        $this->store('rs-test-product', 'Product', self::PRODUCT, ['product']);
        $this->store('rs-test-breadcrumbs', 'Breadcrumbs', self::BREADCRUMBS, ['product']);

        $html = $this->page('product');
        $stored = $this->storedPage();

        self::assertStringContainsString('data-snippet="rs-test-breadcrumbs"', $stored);
        foreach ([$html, $stored] as $copy) {
            $this->assertNoProductMicrodata($copy);
            self::assertStringContainsString(self::BREADCRUMB_WITHOUT_MICRODATA, $copy);
            self::assertStringContainsString(self::ORGANIZATION_ITEM, $copy, 'An item no snippet writes stays.');
        }
    }

    /**
     * The items no snippet replaces stay on the page, and the page test does not read them as the theme's own
     * Product markup
     *
     * @return void
     */
    public function testAPageTestOfTheProductPageFindsNoThemeProductMarkupOnceItIsRemoved(): void
    {
        $this->store('rs-test-product', 'Product', self::PRODUCT, ['product']);
        $html = $this->page('product');
        self::assertStringContainsString(self::ORGANIZATION_ITEM, $html, 'An item no snippet writes stays.');

        $pageTest = $this->_objectManager->create(PageTest::class, ['reader' => new FixedStorefrontReader($html)]);
        self::assertInstanceOf(PageTestInterface::class, $pageTest);
        $result = $pageTest->testPage($this->get(PageEntityFactoryInterface::class)->create(
            'product',
            $this->fixtureId('product'),
            $this->storeId()
        ));

        $observation = $this->get(ThemeMarkupObservationsInterface::class)->latest($this->storeId(), 'product');
        self::assertNotNull($observation);
        self::assertFalse($observation->isFound());
        self::assertSame([], $observation->getItems());
        self::assertSame([], $result->getThemeMarkup(), 'The result lists the same items the observation keeps.');
        $answer = $this->get(PageTestPresenter::class)->present($result);
        self::assertFalse($answer['themeMarkup']['found']);
        self::assertSame('', $answer['themeMarkup']['text'], 'No notice points at a setting that cannot help.');
    }

    public function testTheStoredProductPageKeepsTheThemeMicrodataWhenNoProductSnippetIsWritten(): void
    {
        $this->enablePageCache();
        $this->store('rs-test-everywhere', 'Everywhere', self::EVERYWHERE, self::ALL_KINDS);

        $this->page('product');
        $stored = $this->storedPage();

        self::assertStringContainsString('data-snippet="rs-test-everywhere"', $stored);
        self::assertMatchesRegularExpression('~<[a-z][^>]*\sitemprop="~i', $stored);
    }

    /**
     * Assert a product page carries no Product item of the theme, nor any property of it
     *
     * @param string $html
     * @return void
     */
    private function assertNoProductMicrodata(string $html): void
    {
        self::assertDoesNotMatchRegularExpression('~<[a-z][^>]*\sitemtype="[^"]*schema\.org/Product"~i', $html);
        self::assertDoesNotMatchRegularExpression('~<[a-z][^>]*\sitemtype="[^"]*schema\.org/Offer"~i', $html);
        self::assertDoesNotMatchRegularExpression('~<body[^>]*\sitemscope~i', $html);
        self::assertDoesNotMatchRegularExpression(
            '~<[a-z][^>]*\sitemprop="(price|priceCurrency|offers|sku|image|description)"~i',
            $html
        );
        self::assertDoesNotMatchRegularExpression('~<h1[^>]*>\s*<span[^>]*\sitemprop=~i', $html);
    }

    /**
     * Store a switched-on page snippet set for kinds of page in every store view
     *
     * @param string $code
     * @param string $name
     * @param string $template
     * @param list<string> $kinds
     * @return void
     */
    private function store(string $code, string $name, string $template, array $kinds): void
    {
        $snippet = $this->get(SnippetFactoryInterface::class)->draft($code, $name, false);
        $snippet->changeTemplate($template, $this->get(TemplateTypeInterface::class));
        $snippet->place(
            $kinds,
            $this->get(ConditionTreeParserInterface::class)->parse(''),
            [0],
            $this->get(PlacementVocabularyInterface::class)
        );
        $snippet->switchOn();
        $this->get(SnippetRootRepositoryInterface::class)->save($snippet);
        $this->get(RichSnippetsCache::class)->clean();
    }

    /**
     * Give a stored snippet a template that does not compile, as a damaged row would hold
     *
     * @param string $code
     * @return void
     */
    private function breakTemplate(string $code): void
    {
        $connection = $this->get(ResourceConnection::class)->getConnection();
        $connection->update(
            $connection->getTableName(SnippetResource::TABLE),
            ['template' => '{"@type": "Thing", "name": "{{ ( }}"}'],
            ['code = ?' => $code]
        );
    }

    /**
     * The HTML of a page of a kind, rendered for a visitor of the default store view
     *
     * @param string $kind
     * @return string
     */
    private function page(string $kind): string
    {
        $paths = [
            'home' => '/',
            'product' => 'catalog/product/view/id/' . $this->fixtureId('product'),
            'category' => 'catalog/category/view/id/' . $this->fixtureId('category'),
            'cms' => 'cms/page/view/page_id/' . $this->fixtureId('page'),
            'other' => 'customer/account/login',
        ];
        $this->dispatch($paths[$kind]);
        $response = $this->getResponse();
        self::assertInstanceOf(HttpResponse::class, $response);
        $html = (string) $response->getBody();
        self::assertStringContainsString('</html>', $html, 'The ' . $kind . ' page was rendered.');

        return $html;
    }

    /**
     * A page split at the end of its head
     *
     * @param string $html
     * @return array{string, string}
     */
    private function headAndBody(string $html): array
    {
        $parts = explode('</head>', $html, 2);
        self::assertCount(2, $parts, 'The page has a head.');

        return [$parts[0], $parts[1]];
    }

    /**
     * Switch the built-in page cache on, empty
     *
     * @return void
     */
    private function enablePageCache(): void
    {
        $state = $this->get(StateInterface::class);
        $this->pageCacheWasEnabled = $state->isEnabled(PageCache::TYPE_IDENTIFIER);
        $state->setEnabled(PageCache::TYPE_IDENTIFIER, true);
        $this->get(PageCache::class)->clean();
    }

    /**
     * The page cache tags the last response carries
     *
     * @return list<string>
     */
    private function tagsHeader(): array
    {
        $response = $this->getResponse();
        self::assertInstanceOf(HttpResponse::class, $response);
        $header = $response->getHeader('X-Magento-Tags');
        self::assertIsObject($header, 'The response carries page cache tags.');

        return explode(',', (string) $header->getFieldValue());
    }

    /**
     * The body of the page the built-in page cache stored for the last request
     *
     * @return string
     */
    private function storedPage(): string
    {
        $stored = $this->get(Kernel::class)->load();
        self::assertInstanceOf(HttpResponse::class, $stored, 'The page cache stored the page.');
        $content = $stored->getContent();
        self::assertIsString($content);

        return $content;
    }

    /**
     * The ID of a fixture
     *
     * @param string $name
     * @return int
     */
    private function fixtureId(string $name): int
    {
        $fixture = DataFixtureStorageManager::getStorage()->get($name);
        self::assertInstanceOf(AbstractModel::class, $fixture);
        $id = $fixture->getId();
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
        return (int) $this->get(StoreManagerInterface::class)->getDefaultStoreView()?->getId();
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
        $service = $this->_objectManager->get($type);
        self::assertInstanceOf($type, $service);

        return $service;
    }
}
