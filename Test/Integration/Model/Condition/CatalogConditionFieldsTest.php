<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Test\Integration\Model\Condition;

use Hryvinskyi\RichSnippets\Api\ConditionFieldPoolInterface;
use Hryvinskyi\RichSnippets\Api\ConditionSubjectInterface;
use Hryvinskyi\RichSnippets\Api\ConditionTreeParserInterface;
use Hryvinskyi\RichSnippets\Api\Data\PageEntityInterface;
use Hryvinskyi\RichSnippets\Api\ExistingPagesInterface;
use Hryvinskyi\RichSnippets\Api\PageEntityFactoryInterface;
use Hryvinskyi\RichSnippets\Api\PlacementCounterInterface;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Test\Fixture\AttributeSet as AttributeSetFixture;
use Magento\Catalog\Test\Fixture\Category as CategoryFixture;
use Magento\Catalog\Test\Fixture\Product as ProductFixture;
use Magento\Catalog\Test\Fixture\Virtual as VirtualProductFixture;
use Magento\Cms\Test\Fixture\Page as PageFixture;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DataObject;
use Magento\Framework\Model\AbstractModel;
use Magento\Framework\ObjectManagerInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Fixture\DataFixtureStorageManager;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * The condition fields, the pages a store view has and the placement counts, over a catalog built for the test
 *
 * The attribute set and the categories are created here, so the counts over them are exact whatever else the test
 * database holds: two pages in the set (a simple and a virtual product; a disabled and an invisible one in the set
 * are no pages), three product pages in "Bags", one in the inactive "Hidden".
 */
#[
    DbIsolation(true),
    DataFixture(AttributeSetFixture::class, as: 'set'),
    DataFixture(CategoryFixture::class, ['name' => 'Bags RS'], as: 'bags'),
    DataFixture(CategoryFixture::class, ['name' => 'Hidden RS', 'is_active' => false], as: 'hidden'),
    DataFixture(
        ProductFixture::class,
        ['attribute_set_id' => '$set.attribute_set_id$', 'category_ids' => ['$bags.id$', '$hidden.id$']],
        as: 'simple'
    ),
    DataFixture(
        VirtualProductFixture::class,
        ['attribute_set_id' => '$set.attribute_set_id$', 'category_ids' => ['$bags.id$']],
        as: 'virtual'
    ),
    DataFixture(
        ProductFixture::class,
        [
            'attribute_set_id' => '$set.attribute_set_id$',
            'status' => Status::STATUS_DISABLED,
            'category_ids' => ['$bags.id$'],
        ],
        as: 'disabled'
    ),
    DataFixture(
        ProductFixture::class,
        ['attribute_set_id' => '$set.attribute_set_id$', 'visibility' => Visibility::VISIBILITY_NOT_VISIBLE],
        as: 'invisible'
    ),
    DataFixture(ProductFixture::class, ['category_ids' => ['$bags.id$']], as: 'other'),
    DataFixture(PageFixture::class, ['identifier' => 'rs-test-faq', 'title' => 'FAQ RS'], as: 'faq'),
    DataFixture(
        PageFixture::class,
        ['identifier' => 'rs-test-draft', 'title' => 'Draft RS', 'active' => false],
        as: 'draft'
    ),
]
class CatalogConditionFieldsTest extends TestCase
{
    /**
     * @var ObjectManagerInterface
     */
    private ObjectManagerInterface $objectManager;

    /**
     * @var int
     */
    private int $storeId = 0;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        $this->objectManager = Bootstrap::getObjectManager();
        $this->storeId = (int) $this->get(StoreManagerInterface::class)->getDefaultStoreView()?->getId();
    }

    public function testTheProductPagesAreTheEnabledVisibleProductsOfTheWebsite(): void
    {
        $ids = $this->pageIds('product');

        foreach (['simple', 'virtual', 'other'] as $page) {
            self::assertContains($this->id($page), $ids, $page . ' is a product page');
        }
        foreach (['disabled', 'invisible'] as $notPage) {
            self::assertNotContains($this->id($notPage), $ids, $notPage . ' is no product page');
        }
    }

    public function testTheCategoryAndCmsPagesAreTheActiveOnesWithoutTheHomePage(): void
    {
        self::assertContains($this->id('bags'), $this->pageIds('category'));
        self::assertNotContains($this->id('hidden'), $this->pageIds('category'));
        $cms = $this->pageIds('cms');
        self::assertContains($this->id('faq'), $cms);
        self::assertNotContains($this->id('draft'), $cms);
        self::assertNotContains($this->homePageId(), $cms, 'The home page is a page of the home kind only.');
    }

    public function testTheHomePageIsOnePageShowingTheHomeCmsPageAndEveryOtherPageCannotBeListed(): void
    {
        self::assertSame([$this->homePageId()], $this->pageIds('home'));
        self::assertNull($this->get(ExistingPagesInterface::class)->select('other', $this->storeId));
    }

    public function testTheProductTypeOfAProductPage(): void
    {
        $type = $this->subject('product_type');

        self::assertSame(['simple'], $type->pageValues($this->page('product', 'simple')));
        self::assertSame(['virtual'], $type->pageValues($this->page('product', 'virtual')));
        self::assertSame('Virtual Product', $type->valueLabel('virtual', $this->storeId));
        self::assertSame(
            ['simple' => 'Simple Product', 'virtual' => 'Virtual Product'],
            array_intersect_key($this->options('product_type'), ['simple' => true, 'virtual' => true])
        );
    }

    public function testTheAttributeSetOfAProductPage(): void
    {
        $set = (string) $this->setId();

        self::assertSame([$set], $this->subject('attribute_set')->pageValues($this->page('product', 'virtual')));
        self::assertNotSame([$set], $this->subject('attribute_set')->pageValues($this->page('product', 'other')));
        self::assertSame($this->attributeSetName(), $this->subject('attribute_set')->valueLabel($set, $this->storeId));
        self::assertSame($this->attributeSetName(), $this->options('attribute_set')[$set] ?? null);
    }

    public function testTheCategoriesOfAProductPageIncludeTheCategoriesAboveThem(): void
    {
        $categories = $this->subject('product_category')->pageValues($this->page('product', 'simple'));
        $bags = (string) $this->id('bags');

        self::assertContains($bags, $categories);
        self::assertContains((string) $this->id('hidden'), $categories, 'An inactive category still groups products.');
        self::assertContains($this->parentOf('bags'), $categories, 'A category above the product holds it too.');
        self::assertSame('Bags RS', $this->subject('product_category')->valueLabel($bags, $this->storeId));
        self::assertSame('Bags RS', $this->options('product_category')[$bags] ?? null);
    }

    public function testTheCategoryOfACategoryPageIsItsOwnOnly(): void
    {
        $bags = (string) $this->id('bags');

        self::assertSame([$bags], $this->subject('category_page')->pageValues($this->page('category', 'bags')));
        self::assertSame('Bags RS', $this->subject('category_page')->valueLabel($bags, $this->storeId));
        self::assertFalse($this->subject('category_page')->namesOwnValue());
    }

    public function testTheIdentifierOfACmsPage(): void
    {
        self::assertSame(['rs-test-faq'], $this->subject('cms_page')->pageValues($this->page('cms', 'faq')));
        self::assertSame('FAQ RS', $this->subject('cms_page')->valueLabel('rs-test-faq', $this->storeId));
        self::assertSame('FAQ RS', $this->options('cms_page')['rs-test-faq'] ?? null);
    }

    public function testAProductAttributeIsReadFromTheProductsValue(): void
    {
        $weight = $this->fields()->get('product_attribute')->subject('weight');
        self::assertNotNull($weight);
        $values = $weight->pageValues($this->page('product', 'simple'));

        self::assertCount(1, $values);
        self::assertEqualsWithDelta(1.0, (float) $values[0], 0.0001);
        self::assertSame(
            [],
            $weight->pageValues($this->page('product', 'virtual')),
            'A virtual product weighs nothing.'
        );
        self::assertSame(['product.weight'], $weight->getVariablePaths());
        self::assertNull($this->fields()->get('product_attribute')->subject('tier_price'));
    }

    public function testAFieldDoesNotAnswerForAPageOfAnotherKind(): void
    {
        self::assertSame([], $this->subject('product_category')->pageValues($this->page('category', 'bags')));
    }

    public function testAPlacementIsCountedKindByKindOverThePagesOfEach(): void
    {
        $set = (string) $this->setId();
        $bags = (string) $this->id('bags');
        $products = $this->placementCount(['product'], 'all', [])['product'];
        $cmsPages = $this->placementCount(['cms'], 'all', [])['cms'];

        self::assertSame($products[1], $products[0], 'Without conditions every page matches.');
        self::assertGreaterThanOrEqual(3, $products[1]);
        self::assertSame(
            [2, $products[1]],
            $this->placementCount(['product'], 'all', [$this->leaf('attribute_set', '==', $set)])['product']
        );
        self::assertSame(
            [1, $products[1]],
            $this->placementCount(['product'], 'all', [
                $this->leaf('attribute_set', '==', $set),
                $this->leaf('product_type', '==', 'virtual'),
            ])['product']
        );
        self::assertSame(
            [1, $products[1]],
            $this->placementCount(['product'], 'all', [
                $this->leaf('attribute_set', '==', $set),
                $this->leaf('product_type', '!=', 'virtual'),
            ])['product']
        );
        self::assertSame(
            [3, $products[1]],
            $this->placementCount(['product'], 'any', [
                $this->leaf('attribute_set', '==', $set),
                $this->leaf('product_category', '()', $bags),
            ])['product']
        );
        self::assertSame(
            ['product' => [2, $products[1]], 'cms' => [1, $cmsPages[1]]],
            $this->placementCount(['product', 'cms'], 'all', [
                $this->leaf('attribute_set', '==', $set),
                $this->leaf('cms_page', '==', 'rs-test-faq'),
            ]),
            'Each condition is asked of the pages of its own kind only.'
        );
        self::assertSame(
            1,
            $this->placementCount(['category'], 'all', [$this->leaf('category_page', '()', $bags)])['category'][0]
        );
        self::assertSame([], $this->placementCount(['other'], 'all', []), 'Every other page cannot be counted.');
    }

    public function testNestedGroupsAncestorsAndProductAttributesAreCountedAsTheyAreEvaluated(): void
    {
        $set = (string) $this->setId();
        $products = $this->placementCount(['product'], 'all', [])['product'][1];

        // all of: attribute set is the test's, and any of (weight at least 1, product type virtual)
        self::assertSame([2, $products], $this->placementCount(['product'], 'all', [
            $this->leaf('attribute_set', '==', $set),
            $this->group('any', [
                $this->leaf('product_attribute', '>=', '1', 'weight'),
                $this->leaf('product_type', '==', 'virtual'),
            ]),
        ])['product']);
        // a category above Bags holds every product in Bags
        self::assertGreaterThanOrEqual(3, $this->placementCount(['product'], 'all', [
            $this->leaf('product_category', '()', $this->parentOf('bags')),
        ])['product'][0]);
        // the SKU containing the virtual fixture's prefix, in another case
        self::assertSame([1, $products], $this->placementCount(['product'], 'all', [
            $this->leaf('attribute_set', '==', $set),
            $this->leaf('product_attribute', '{}', 'VIRTUAL-PRODUCT', 'sku'),
        ])['product']);
        // a condition still without a value is not asked
        self::assertSame([2, $products], $this->placementCount(['product'], 'all', [
            $this->leaf('attribute_set', '==', $set),
            $this->leaf('product_type', '==', ''),
        ])['product']);
    }

    /**
     * Count a placement in the default store view
     *
     * @param list<string> $kinds
     * @param string $aggregator `all` or `any`
     * @param list<array<string, mixed>> $conditions the outer group's conditions and groups
     * @return array<string, array{int, int}> matching, then total, by kind
     */
    private function placementCount(array $kinds, string $aggregator, array $conditions): array
    {
        $tree = $this->get(ConditionTreeParserInterface::class)->parseDecoded(
            $conditions === [] ? [] : $this->group($aggregator, $conditions)
        );
        $counts = [];
        $counter = $this->get(PlacementCounterInterface::class);
        foreach ($counter->count($kinds, $tree, $this->storeId) as $kind => $count) {
            $counts[$kind] = [$count->getMatching(), $count->getTotal()];
        }

        return $counts;
    }

    /**
     * A group of the builder's tree
     *
     * @param string $aggregator
     * @param list<array<string, mixed>> $conditions
     * @return array<string, mixed>
     */
    private function group(string $aggregator, array $conditions): array
    {
        return [
            'type' => 'rich_snippets/combine',
            'attribute' => null,
            'operator' => null,
            'value' => '1',
            'is_value_processed' => null,
            'aggregator' => $aggregator,
            'conditions' => $conditions,
        ];
    }

    /**
     * A condition of the builder's tree
     *
     * @param string $field
     * @param string $operator
     * @param string $value
     * @param string|null $attribute
     * @return array<string, mixed>
     */
    private function leaf(string $field, string $operator, string $value, ?string $attribute = null): array
    {
        return [
            'type' => $field,
            'attribute' => $attribute,
            'operator' => $operator,
            'value' => $value,
            'is_value_processed' => null,
        ];
    }

    /**
     * What a field of one subject reads
     *
     * @param string $field
     * @return ConditionSubjectInterface
     */
    private function subject(string $field): ConditionSubjectInterface
    {
        $subject = $this->fields()->get($field)->subject(null);
        self::assertNotNull($subject);

        return $subject;
    }

    /**
     * A field's option labels in the default store view, by value
     *
     * @param string $field
     * @return array<int|string, string>
     */
    private function options(string $field): array
    {
        $labels = [];
        foreach ($this->fields()->get($field)->options(null, $this->storeId) ?? [] as $option) {
            $labels[(string) $option['value']] = (string) $option['label'];
        }

        return $labels;
    }

    /**
     * The ID of the category above a fixture category
     *
     * @param string $fixture
     * @return string
     */
    private function parentOf(string $fixture): string
    {
        $category = DataFixtureStorageManager::getStorage()->get($fixture);
        self::assertInstanceOf(DataObject::class, $category);
        $parent = $category->getData('parent_id');
        self::assertIsNumeric($parent);

        return (string) $parent;
    }

    /**
     * The entity IDs of the default store view's pages of a kind
     *
     * @param string $kind
     * @return list<int>
     */
    private function pageIds(string $kind): array
    {
        $select = $this->get(ExistingPagesInterface::class)->select($kind, $this->storeId);
        self::assertNotNull($select);

        return array_values(array_map(
            static fn (mixed $id): int => is_numeric($id) ? (int) $id : 0,
            $this->get(ResourceConnection::class)->getConnection()->fetchCol($select)
        ));
    }

    /**
     * A page of a kind showing a fixture entity, in the default store view
     *
     * @param string $kind
     * @param string $fixture
     * @return PageEntityInterface
     */
    private function page(string $kind, string $fixture): PageEntityInterface
    {
        return $this->get(PageEntityFactoryInterface::class)->create($kind, $this->id($fixture), $this->storeId);
    }

    /**
     * @return ConditionFieldPoolInterface
     */
    private function fields(): ConditionFieldPoolInterface
    {
        return $this->get(ConditionFieldPoolInterface::class);
    }

    /**
     * @return int
     */
    private function setId(): int
    {
        $set = DataFixtureStorageManager::getStorage()->get('set');
        self::assertInstanceOf(DataObject::class, $set);
        $id = $set->getData('attribute_set_id');
        self::assertIsNumeric($id);

        return (int) $id;
    }

    /**
     * @return string
     */
    private function attributeSetName(): string
    {
        $set = DataFixtureStorageManager::getStorage()->get('set');
        self::assertInstanceOf(DataObject::class, $set);
        $name = $set->getData('attribute_set_name');
        self::assertIsString($name);

        return $name;
    }

    /**
     * The ID of the CMS page the default store view shows as its home page
     *
     * @return int
     */
    private function homePageId(): int
    {
        $resource = $this->get(ResourceConnection::class);
        $connection = $resource->getConnection();
        $id = $connection->fetchCol(
            $connection->select()
                ->from($resource->getTableName('cms_page'), ['page_id'])
                ->where('identifier = ?', 'home')
        )[0] ?? null;
        self::assertIsNumeric($id, 'The installation has a CMS page "home".');

        return (int) $id;
    }

    /**
     * The ID of a fixture entity
     *
     * @param string $name
     * @return int
     */
    private function id(string $name): int
    {
        $fixture = DataFixtureStorageManager::getStorage()->get($name);
        self::assertInstanceOf(AbstractModel::class, $fixture);
        $id = $fixture->getId();
        self::assertIsNumeric($id);

        return (int) $id;
    }

    /**
     * A service from the container, of the type asked for
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
