<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Test\Integration\Model\Library;

use Hryvinskyi\JsonTemplate\Api\TemplateEngineInterface;
use Hryvinskyi\RichSnippets\Api\CollectionLimitsInterface;
use Hryvinskyi\RichSnippets\Api\Data\AddedSnippetInterface;
use Hryvinskyi\RichSnippets\Api\Data\FeatureCheckInterface;
use Hryvinskyi\RichSnippets\Api\Data\FeatureVerdict;
use Hryvinskyi\RichSnippets\Api\Data\MissingPropertyInterface;
use Hryvinskyi\RichSnippets\Api\FrontendEmulationInterface;
use Hryvinskyi\RichSnippets\Api\LibraryInterface;
use Hryvinskyi\RichSnippets\Api\PageDataPrefetchInterface;
use Hryvinskyi\RichSnippets\Api\PageEntityFactoryInterface;
use Hryvinskyi\RichSnippets\Api\PageScopeFactoryInterface;
use Hryvinskyi\RichSnippets\Api\RequirementCheckerInterface;
use Hryvinskyi\RichSnippets\Api\SnippetRepositoryInterface;
use Hryvinskyi\RichSnippets\Model\Cache\Type\RichSnippetsCache;
use Hryvinskyi\RichSnippets\Model\Catalog\ProductAttributes;
use Hryvinskyi\RichSnippets\Model\Engine\IncludedTemplates;
use Hryvinskyi\RichSnippets\Model\Engine\SnippetIncludeResolver;
use Hryvinskyi\RichSnippets\Model\Page\Cms\StoreCmsPagesInterface;
use Hryvinskyi\RichSnippets\Model\ResourceModel\IdempotencyEntry;
use Hryvinskyi\RichSnippets\Model\ResourceModel\Snippet as SnippetResource;
use Hryvinskyi\RichSnippets\Model\Snippet\SnippetRootRepositoryInterface;
use Hryvinskyi\RichSnippets\Model\Snippet\TemplateTypeInterface;
use Magento\Catalog\Test\Fixture\Attribute as AttributeFixture;
use Magento\Catalog\Test\Fixture\Category as CategoryFixture;
use Magento\Catalog\Test\Fixture\Product as ProductFixture;
use Magento\Cms\Test\Fixture\Page as PageFixture;
use Magento\ConfigurableProduct\Test\Fixture\Attribute as ConfigurableAttributeFixture;
use Magento\ConfigurableProduct\Test\Fixture\Product as ConfigurableProductFixture;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DataObject;
use Magento\Framework\ObjectManager\ResetAfterRequestInterface;
use Magento\Framework\ObjectManagerInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Fixture\DataFixtureStorageManager;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * Each library template, added with its suggested answers and evaluated on a real page of its kind, writes a block
 * that meets every kind of Google result it is for, as far as the page's data goes; a template whose items the admin
 * types in (FAQ) writes nothing as added and meets its results once its items are filled
 *
 * The price and category product indexes are filled once a product's save commits, so the test runs outside an
 * isolating transaction; the fixtures remove themselves and the test removes the snippets and keys it added.
 */
#[
    DbIsolation(false),
    DataFixture(AttributeFixture::class, ['attribute_code' => 'ean', 'default_frontend_label' => 'EAN'], as: 'ean'),
    DataFixture(CategoryFixture::class, ['name' => 'Bags'], as: 'bags'),
    DataFixture(
        ProductFixture::class,
        [
            'name' => 'Field satchel',
            'sku' => 'lib-satchel',
            'price' => 240,
            'ean' => '4062001000010',
            'short_description' => 'A <b>full-grain</b> leather satchel.',
            'description' => 'A <b>full-grain</b> leather satchel.',
            'category_ids' => ['$bags.id$'],
            'media_gallery_entries' => [[]],
        ],
        as: 'satchel'
    ),
    DataFixture(ConfigurableAttributeFixture::class, as: 'size'),
    DataFixture(
        ProductFixture::class,
        ['sku' => 'lib-wk-s', 'price' => 310, 'ean' => '4062001000034', 'media_gallery_entries' => [[]]],
        as: 'small'
    ),
    DataFixture(
        ProductFixture::class,
        ['sku' => 'lib-wk-l', 'price' => 290, 'ean' => '4062001000041', 'media_gallery_entries' => [[]]],
        as: 'large'
    ),
    DataFixture(
        ConfigurableProductFixture::class,
        ['name' => 'Weekender', 'sku' => 'lib-wk', '_options' => ['$size$'], '_links' => ['$small$', '$large$']],
        as: 'weekender'
    ),
    DataFixture(PageFixture::class, ['identifier' => 'lib-faq', 'title' => 'FAQ'], as: 'faq'),
]
class LibraryTemplatesOnPagesTest extends TestCase
{
    private const PAGE_TEMPLATES = [
        'organization' => ['home', ''],
        'website-name' => ['home', ''],
        'breadcrumbs' => ['product', 'satchel'],
        'local-business' => ['cms', 'faq'],
        'product' => ['product', 'satchel'],
        'product-variants' => ['product', 'weekender'],
        'product-list' => ['category', 'bags'],
        'category' => ['category', 'bags'],
        'faq' => ['cms', 'faq'],
        'article' => ['cms', 'faq'],
    ];

    /**
     * The templates whose items the admin types in, which write nothing until they are filled
     */
    private const TYPED_IN_TEMPLATES = ['faq'];

    /**
     * @var ObjectManagerInterface
     */
    private ObjectManagerInterface $objectManager;

    /**
     * @var list<int> the snippets the test added
     */
    private array $added = [];

    /**
     * @var list<string> the idempotency keys the test used
     */
    private array $keys = [];

    /**
     * @return void
     */
    protected function setUp(): void
    {
        $this->objectManager = Bootstrap::getObjectManager();
        $this->get(RichSnippetsCache::class)->clean();
        $this->get(ProductAttributes::class)->_resetState();
        foreach (['HryvinskyiRichSnippetsProductAttributeJoin', 'HryvinskyiRichSnippetsProductValues'] as $memo) {
            $reader = $this->objectManager->get($memo);
            self::assertInstanceOf(ResetAfterRequestInterface::class, $reader);
            $reader->_resetState();
        }
        $this->get(PageDataPrefetchInterface::class)->reset();
    }

    /**
     * @return void
     */
    protected function tearDown(): void
    {
        $connection = $this->get(ResourceConnection::class)->getConnection();
        if ($this->added !== []) {
            $connection->delete(
                $connection->getTableName(SnippetResource::TABLE),
                [SnippetResource::ID . ' IN (?)' => $this->added]
            );
        }
        if ($this->keys !== []) {
            $connection->delete(
                $connection->getTableName(IdempotencyEntry::TABLE),
                [IdempotencyEntry::KEY . ' IN (?)' => $this->keys]
            );
        }
        $this->get(SnippetIncludeResolver::class)->_resetState();
        $this->get(PageDataPrefetchInterface::class)->reset();
    }

    public function testEveryPageTemplateWritesABlockThatMeetsTheResultsItIsFor(): void
    {
        $copies = $this->addEveryPageTemplate();

        foreach (self::PAGE_TEMPLATES as $code => [$kind, $fixture]) {
            if (in_array($code, self::TYPED_IN_TEMPLATES, true)) {
                $this->fillTypedInItems($copies[$code]);
            }
            $snippet = $this->get(SnippetRepositoryInterface::class)->get($copies[$code]);
            $output = $this->evaluate($snippet->getTemplate(), $kind, $this->entityId($kind, $fixture));

            self::assertIsArray($output, $code . ' writes a block.');
            self::assertNotSame([], $output, $code . ' writes a block.');
            self::assertSame($snippet->getType(), $output['@type'] ?? null, $code);
            foreach ($this->get(RequirementCheckerInterface::class)->checkOutput($snippet->getType(), $output)
                ->getFeatures() as $feature) {
                self::assertNotSame(FeatureVerdict::NotMet, $feature->getVerdict(), $this->describe($code, $feature));
            }
        }
    }

    public function testAnFaqCopyLeftAsAddedIsNotOutputUntilItsQuestionsAreTypedIn(): void
    {
        $copies = $this->addEveryPageTemplate();
        $faq = $this->get(SnippetRepositoryInterface::class)->get($copies['faq']);

        $output = $this->evaluate($faq->getTemplate(), 'cms', $this->entityId('cms', 'faq'));

        self::assertEmpty($output, 'Two empty question rows leave the whole block out.');
    }

    public function testTheProductTemplatesCarryTheAnswersSuggestedFromTheCatalog(): void
    {
        $copies = $this->addEveryPageTemplate();

        $product = $this->blockOf($copies['product'], 'product', $this->entityId('product', 'satchel'));
        $group = $this->blockOf($copies['product-variants'], 'product', $this->entityId('product', 'weekender'));
        $organization = $this->blockOf($copies['organization'], 'home', $this->entityId('home', ''));

        self::assertSame('4062001000010', $product['gtin13'] ?? null, 'The EAN attribute is suggested for the GTIN.');
        self::assertSame(
            'A full-grain leather satchel.',
            $product['description'] ?? null,
            'Whichever description attribute is suggested, its HTML is removed.'
        );
        self::assertIsArray($product['image'] ?? null);
        self::assertIsArray($product['offers'] ?? null);
        self::assertSame('240.00', $product['offers']['price'] ?? null);
        self::assertIsArray($product['offers']['hasMerchantReturnPolicy'] ?? null);
        $size = $this->fixture('size')->getData('attribute_code');
        self::assertIsString($size);
        self::assertSame(['https://schema.org/' . $size], $group['variesBy'] ?? null);
        self::assertIsArray($group['hasVariant'] ?? null);
        self::assertSame(
            ['4062001000034', '4062001000041'],
            array_map(
                static fn (mixed $variant): mixed => is_array($variant) ? ($variant['gtin13'] ?? null) : null,
                $group['hasVariant']
            ),
            'Each variant carries its own EAN.'
        );
        self::assertIsArray($organization['hasMerchantReturnPolicy'] ?? null);
        self::assertSame(30, $organization['hasMerchantReturnPolicy']['merchantReturnDays'] ?? null);
        self::assertArrayNotHasKey('sameAs', $organization, 'An empty list of profiles is left out.');
    }

    /**
     * Type a question and its answer into each item of a copy's question list, as the admin does in the builder
     *
     * @param int $snippetId
     * @return void
     */
    private function fillTypedInItems(int $snippetId): void
    {
        $repository = $this->get(SnippetRootRepositoryInterface::class);
        $snippet = $repository->get($snippetId);
        $template = json_decode($snippet->getTemplate(), true);
        self::assertIsArray($template);
        self::assertIsArray($template['mainEntity'] ?? null);
        self::assertNotSame([], $template['mainEntity'], 'The copy offers rows to type into.');
        $items = [];
        foreach (array_values($template['mainEntity']) as $index => $item) {
            self::assertIsArray($item);
            self::assertIsArray($item['acceptedAnswer'] ?? null);
            $item['name'] = 'Question ' . ($index + 1) . '?';
            $item['acceptedAnswer']['text'] = 'Answer ' . ($index + 1) . '.';
            $items[] = $item;
        }
        $template['mainEntity'] = $items;
        $snippet->changeTemplate(
            (string) json_encode($template, JSON_THROW_ON_ERROR),
            $this->get(TemplateTypeInterface::class)
        );
        $repository->save($snippet);
    }

    /**
     * Add every page template once, with its suggested answers
     *
     * @return array<string, int> the copy's snippet ID by template code
     */
    private function addEveryPageTemplate(): array
    {
        $copies = [];
        foreach (array_keys(self::PAGE_TEMPLATES) as $code) {
            $key = 'test-' . bin2hex(random_bytes(12));
            $this->keys[] = $key;
            $added = $this->get(LibraryInterface::class)->add($code, [], $key);
            foreach ($added as $snippet) {
                if (!$snippet->isReused()) {
                    $this->added[] = $snippet->getId();
                }
            }
            $last = end($added);
            self::assertInstanceOf(AddedSnippetInterface::class, $last);
            $copies[$code] = $last->getId();
        }
        $this->get(SnippetIncludeResolver::class)->_resetState();

        return $copies;
    }

    /**
     * A copy's block on a page, as an object
     *
     * @param int $snippetId
     * @param string $kind
     * @param int $entityId
     * @return array<mixed>
     */
    private function blockOf(int $snippetId, string $kind, int $entityId): array
    {
        $output = $this->evaluate(
            $this->get(SnippetRepositoryInterface::class)->get($snippetId)->getTemplate(),
            $kind,
            $entityId
        );
        self::assertIsArray($output);

        return $output;
    }

    /**
     * A template evaluated for a page of the default store view as its storefront does: the paths it and its includes
     * read are named to the data sources in one batch first
     *
     * @param string $template
     * @param string $kind
     * @param int $entityId
     * @return mixed
     */
    private function evaluate(string $template, string $kind, int $entityId): mixed
    {
        $page = $this->get(PageEntityFactoryInterface::class)->create($kind, $entityId, $this->storeId());
        $engine = $this->objectManager->get('HryvinskyiRichSnippetsTemplateEngine');
        self::assertInstanceOf(TemplateEngineInterface::class, $engine);
        $reached = $this->get(IncludedTemplates::class)->withIncludes([$engine->compile($template)]);
        $paths = [];
        foreach ($reached as $compiled) {
            foreach ($compiled->getPaths() as $path) {
                $paths[$path] = true;
            }
        }

        return $this->get(FrontendEmulationInterface::class)->run(
            $this->storeId(),
            function () use ($engine, $template, $page, $paths, $reached): mixed {
                $this->get(PageDataPrefetchInterface::class)->prefetch(
                    [$page],
                    array_map('strval', array_keys($paths)),
                    $this->get(CollectionLimitsInterface::class)->limits($reached)
                );

                return $engine->evaluate($template, $this->get(PageScopeFactoryInterface::class)->create($page));
            }
        );
    }

    /**
     * Why a result is not met, for the failure message
     *
     * @param string $code
     * @param FeatureCheckInterface $feature
     * @return string
     */
    private function describe(string $code, FeatureCheckInterface $feature): string
    {
        return sprintf(
            '%s misses for %s: %s',
            $code,
            $feature->getRule()->getCode(),
            implode(', ', array_map(
                static fn (MissingPropertyInterface $missing): string => $missing->getPath(),
                $feature->getMissingRequired()
            ))
        );
    }

    /**
     * The entity a page of a kind shows
     *
     * @param string $kind
     * @param string $fixture
     * @return int
     */
    private function entityId(string $kind, string $fixture): int
    {
        if ($kind === 'home') {
            return (int) $this->get(StoreCmsPagesInterface::class)->homePageId($this->storeId());
        }
        $id = $this->fixture($fixture)->getData($kind === 'cms' ? 'page_id' : 'entity_id')
            ?? $this->fixture($fixture)->getData('id');
        self::assertIsNumeric($id);

        return (int) $id;
    }

    /**
     * A fixture
     *
     * @param string $name
     * @return DataObject
     */
    private function fixture(string $name): DataObject
    {
        $fixture = DataFixtureStorageManager::getStorage()->get($name);
        self::assertInstanceOf(DataObject::class, $fixture);

        return $fixture;
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
        $service = $this->objectManager->get($type);
        self::assertInstanceOf($type, $service);

        return $service;
    }
}
