<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Test\Integration\Model\PageTest;

use Hryvinskyi\HeadTagManager\Api\HeadTagManagerInterface;
use Hryvinskyi\RichSnippets\Api\Data\ExclusionReason;
use Hryvinskyi\RichSnippets\Api\Data\FeatureVerdict;
use Hryvinskyi\RichSnippets\Api\Data\MicrodataItemInterface;
use Hryvinskyi\RichSnippets\Api\Data\PageEntityInterface;
use Hryvinskyi\RichSnippets\Api\Data\PageTestResultInterface;
use Hryvinskyi\RichSnippets\Api\Data\ReadFailure;
use Hryvinskyi\RichSnippets\Api\Data\TestedBlockInterface;
use Hryvinskyi\RichSnippets\Api\Data\UnwrittenReason;
use Hryvinskyi\RichSnippets\Api\Exception\StorefrontReadException;
use Hryvinskyi\RichSnippets\Api\PageEntityFactoryInterface;
use Hryvinskyi\RichSnippets\Api\PageTestInterface;
use Hryvinskyi\RichSnippets\Api\ThemeMarkupObservationsInterface;
use Hryvinskyi\RichSnippets\Model\Cache\Type\RichSnippetsCache;
use Hryvinskyi\RichSnippets\Model\PageTest\PageTest;
use Hryvinskyi\RichSnippets\Model\Storefront\SnippetOutput;
use Hryvinskyi\RichSnippets\Test\Integration\Fixture\FixedStorefrontReader;
use Hryvinskyi\RichSnippets\Test\Integration\Fixture\StoresTestSnippets;
use Magento\Catalog\Test\Fixture\Product as ProductFixture;
use Magento\Framework\App\Response\Http as HttpResponse;
use Magento\Framework\Model\AbstractModel;
use Magento\Store\Model\StoreManagerInterface;
use Magento\TestFramework\Fixture\AppArea;
use Magento\TestFramework\Fixture\Config;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Fixture\DataFixtureStorageManager;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\TestCase\AbstractController;
use Magento\UrlRewrite\Model\UrlFinderInterface;
use Magento\UrlRewrite\Service\V1\Data\UrlRewrite;

/**
 * A page test of a product reads the HTML the storefront really renders for it: its blocks are attributed to their
 * snippets by the code each script element carries and checked against Google's requirements, the theme's own
 * microdata is noted and kept for the store view, and every other snippet has its reason
 *
 * The product page is dispatched as a visitor's request; its body is what a reader standing in for the network
 * answers, so the test runs without a web server.
 */
#[
    AppArea('frontend'),
    DbIsolation(true),
    DataFixture(ProductFixture::class, ['sku' => 'rs-page-test-satchel', 'name' => 'Field Satchel'], as: 'product'),
]
class PageTestTest extends AbstractController
{
    use StoresTestSnippets;

    private const THEME_BLOCK = '<script type="application/ld+json">{"@type":"Organization","name":"Theme"}</script>';

    /**
     * Start every test with no kept selection and no head element left by an earlier request
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->forgetOutput();
        $this->get(HeadTagManagerInterface::class)->clear();
    }

    /**
     * Drop the kept selection
     *
     * @return void
     */
    protected function tearDown(): void
    {
        $this->get(RichSnippetsCache::class)->clean();
        parent::tearDown();
    }

    #[Config('hryvinskyi_rich_snippets/theme/remove_product_markup', '0', 'store', 'default')]
    public function testAProductPageIsTestedFromTheHtmlTheStorefrontRenders(): void
    {
        $this->storeSnippet('rs-test-product', 'Product', [
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => '{{ product.name }}',
        ], ['kinds' => ['product'], 'active' => true, 'sortOrder' => 1]);
        $this->storeSnippet('rs-test-empty', 'Sale end', [
            '@type' => 'Thing',
            'name' => '{{ product.special_to_date }}',
        ], ['kinds' => ['product'], 'active' => true, 'sortOrder' => 2]);
        $this->storeSnippet('rs-test-home', 'Organization', ['@type' => 'Organization', 'name' => 'Linden'], [
            'kinds' => ['home'],
            'active' => true,
        ]);
        $this->storeSnippet('rs-test-off', 'Product list', ['@type' => 'ItemList', 'name' => 'List'], [
            'kinds' => ['product'],
        ]);
        $this->forgetOutput();
        $html = $this->productPage();
        $this->storeSnippet('rs-test-late', 'Added later', ['@type' => 'Thing', 'name' => '{{ product.name }}'], [
            'kinds' => ['product'],
            'active' => true,
            'sortOrder' => 3,
        ]);
        $this->forgetOutput();
        $reader = new FixedStorefrontReader(str_replace('</body>', self::THEME_BLOCK . '</body>', $html));

        $result = $this->pageTest($reader)->testPage($this->product());

        self::assertCount(1, $reader->reads);
        self::assertSame($result->getPath(), $reader->reads[0]['path']);
        self::assertStringStartsWith('/', $result->getPath());
        self::assertSame('Field Satchel', $result->getLabel());
        self::assertNull($result->getFailure());
        $blocks = $this->blocksByCode($result);
        self::assertSame(['rs-test-product', ''], array_keys($blocks));
        self::assertSame('Product', $blocks['rs-test-product']->getSnippet()?->getName());
        self::assertSame(
            ['@context' => 'https://schema.org', '@type' => 'Product', 'name' => 'Field Satchel'],
            $blocks['rs-test-product']->getBlock()->getValue()
        );
        self::assertSame(FeatureVerdict::NotMet, $this->verdict($blocks['rs-test-product'], 'merchant_listing'));
        self::assertNull($blocks['']->getSnippet(), 'A block without a code was not written by Rich Snippets.');
        self::assertSame('Organization', $blocks['']->getType());
        self::assertSame(
            ['rs-test-home' => ExclusionReason::OtherKinds, 'rs-test-off' => ExclusionReason::SwitchedOff],
            $this->exclusions($result)
        );
        self::assertSame(
            ['rs-test-empty' => UnwrittenReason::Empty, 'rs-test-late' => UnwrittenReason::NotInPage],
            $this->unwritten($result)
        );
        self::assertSame('Product', ($result->getThemeMarkup()[0] ?? null)?->getType());
    }

    public function testWhatThePageTestSawOfTheThemeMarkupIsKeptForTheStoreViewAndKind(): void
    {
        $html = $this->productPage();
        $observations = $this->get(ThemeMarkupObservationsInterface::class);

        $result = $this->pageTest(new FixedStorefrontReader($html))->testPage($this->product());

        $observation = $observations->latest($this->storeId(), 'product');
        self::assertNotNull($observation);
        self::assertTrue($observation->isFound());
        self::assertSame($result->getReadPath(), $observation->getPath());
        self::assertSame($this->items($result->getThemeMarkup()), $this->items($observation->getItems()));

        $this->pageTest(new FixedStorefrontReader('<html><body>No markup</body></html>'))->testPage($this->product());

        $replaced = $observations->latest($this->storeId(), 'product');
        self::assertNotNull($replaced);
        self::assertFalse($replaced->isFound(), 'A test that finds nothing replaces the one before.');
        self::assertNull($observations->latest($this->storeId(), 'category'));
    }

    public function testAPageThatCannotBeReadSaysWhyAndKeepsNoObservation(): void
    {
        $reader = new FixedStorefrontReader('', new StorefrontReadException(ReadFailure::HttpStatus, 503));

        $result = $this->pageTest($reader)->testPage($this->product());

        self::assertSame([ReadFailure::HttpStatus, 503], [$result->getFailure(), $result->getHttpStatus()]);
        self::assertNull($result->getReadPath());
        self::assertSame([], $result->getBlocks());
        self::assertSame([], $result->getExclusions());
        self::assertNull($this->get(ThemeMarkupObservationsInterface::class)->latest($this->storeId(), 'product'));
    }

    public function testAPathIsTestedAsThePageItShows(): void
    {
        $test = $this->pageTest(new FixedStorefrontReader('<html><body></body></html>'));
        $rewrite = $this->get(UrlFinderInterface::class)->findOneByData([
            UrlRewrite::ENTITY_TYPE => 'product',
            UrlRewrite::ENTITY_ID => $this->productId(),
            UrlRewrite::STORE_ID => $this->storeId(),
        ]);
        self::assertNotNull($rewrite, 'The product has an address of its own.');

        $product = $test->testPath('/' . $rewrite->getRequestPath(), $this->storeId())->getPage();
        $cart = $test->testPath('/checkout/cart', $this->storeId())->getPage();
        $home = $test->testPath('', $this->storeId())->getPage();

        self::assertSame(['product', $this->productId()], [$product->getKind(), $product->getEntityId()]);
        self::assertSame(['other', null], [$cart->getKind(), $cart->getEntityId()]);
        self::assertSame('home', $home->getKind());
    }

    public function testAPathWithAHostIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->pageTest(new FixedStorefrontReader())->testPath('https://evil.example/', $this->storeId());
    }

    /**
     * The page test as the container builds it, reading through the given reader
     *
     * @param FixedStorefrontReader $reader
     * @return PageTestInterface
     */
    private function pageTest(FixedStorefrontReader $reader): PageTestInterface
    {
        $test = $this->_objectManager->create(PageTest::class, ['reader' => $reader]);
        self::assertInstanceOf(PageTestInterface::class, $test);

        return $test;
    }

    /**
     * The HTML of the fixture product's page, rendered for a visitor of the default store view
     *
     * @return string
     */
    private function productPage(): string
    {
        $this->dispatch('catalog/product/view/id/' . $this->productId());
        $response = $this->getResponse();
        self::assertInstanceOf(HttpResponse::class, $response);
        $html = (string) $response->getBody();
        self::assertStringContainsString('</html>', $html, 'The product page was rendered.');

        return $html;
    }

    /**
     * The blocks of a result by their code, the empty code for a block without one
     *
     * @param PageTestResultInterface $result
     * @return array<string, TestedBlockInterface>
     */
    private function blocksByCode(PageTestResultInterface $result): array
    {
        $blocks = [];
        foreach ($result->getBlocks() as $block) {
            $blocks[$block->getBlock()->getCode() ?? ''] = $block;
        }

        return $blocks;
    }

    /**
     * Microdata items as their types and properties
     *
     * @param list<MicrodataItemInterface> $items
     * @return list<array{string, list<string>}>
     */
    private function items(array $items): array
    {
        return array_map(
            static fn (MicrodataItemInterface $item): array => [$item->getType(), $item->getProperties()],
            $items
        );
    }

    /**
     * The verdict of a block on one kind of result
     *
     * @param TestedBlockInterface $block
     * @param string $rule
     * @return FeatureVerdict|null
     */
    private function verdict(TestedBlockInterface $block, string $rule): ?FeatureVerdict
    {
        foreach ($block->getChecks() as $check) {
            foreach ($check->getFeatures() as $feature) {
                if ($feature->getRule()->getCode() === $rule) {
                    return $feature->getVerdict();
                }
            }
        }

        return null;
    }

    /**
     * The test snippets not set for the page, by code
     *
     * @param PageTestResultInterface $result
     * @return array<string, ExclusionReason>
     */
    private function exclusions(PageTestResultInterface $result): array
    {
        $reasons = [];
        foreach ($result->getExclusions() as $exclusion) {
            if (str_starts_with($exclusion->getSnippet()->getCode(), 'rs-test-')) {
                $reasons[$exclusion->getSnippet()->getCode()] = $exclusion->getReason();
            }
        }

        return $reasons;
    }

    /**
     * The test snippets set for the page that it does not carry, by code
     *
     * @param PageTestResultInterface $result
     * @return array<string, UnwrittenReason>
     */
    private function unwritten(PageTestResultInterface $result): array
    {
        $reasons = [];
        foreach ($result->getUnwritten() as $missing) {
            $reasons[$missing->getSnippet()->getCode()] = $missing->getReason();
        }

        return $reasons;
    }

    /**
     * The fixture product's page in the default store view
     *
     * @return PageEntityInterface
     */
    private function product(): PageEntityInterface
    {
        return $this->get(PageEntityFactoryInterface::class)->create('product', $this->productId(), $this->storeId());
    }

    /**
     * Forget the kept selection and the output of an earlier request
     *
     * @return void
     */
    private function forgetOutput(): void
    {
        $this->get(RichSnippetsCache::class)->clean();
        $this->get(SnippetOutput::class)->_resetState();
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
