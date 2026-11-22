<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Test\Integration\Model\Selection;

use Hryvinskyi\RichSnippets\Api\ConditionTreeParserInterface;
use Hryvinskyi\RichSnippets\Api\Data\ConditionMismatchInterface;
use Hryvinskyi\RichSnippets\Api\Data\ExclusionReason;
use Hryvinskyi\RichSnippets\Api\Data\PageEntityInterface;
use Hryvinskyi\RichSnippets\Api\Data\SnippetExclusionInterface;
use Hryvinskyi\RichSnippets\Api\Data\SnippetInterface;
use Hryvinskyi\RichSnippets\Api\PageEntityFactoryInterface;
use Hryvinskyi\RichSnippets\Api\PlacementVocabularyInterface;
use Hryvinskyi\RichSnippets\Api\SnippetSelectorInterface;
use Hryvinskyi\RichSnippets\Model\Cache\Type\RichSnippetsCache;
use Hryvinskyi\RichSnippets\Model\Snippet\SnippetFactoryInterface;
use Hryvinskyi\RichSnippets\Model\Snippet\SnippetRootRepositoryInterface;
use Hryvinskyi\RichSnippets\Model\Snippet\TemplateTypeInterface;
use Magento\Catalog\Test\Fixture\Product as ProductFixture;
use Magento\Catalog\Test\Fixture\Virtual as VirtualProductFixture;
use Magento\Framework\Model\AbstractModel;
use Magento\Framework\ObjectManagerInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Fixture\DataFixtureStorageManager;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * The snippets a page outputs and the reasons the others are not output, from stored snippets and a real catalog
 *
 * The selection index is emptied before each test, so the stored snippets of the test are what it is built from.
 * Only the test's own snippets (codes starting with `rs-test-`) are looked at.
 */
#[
    DbIsolation(true),
    DataFixture(ProductFixture::class, as: 'simple'),
    DataFixture(VirtualProductFixture::class, as: 'virtual'),
]
class SnippetSelectorTest extends TestCase
{
    private const TEMPLATE = '{"@type": "Product", "name": "{{ product.name }}"}';

    /**
     * @var ObjectManagerInterface
     */
    private ObjectManagerInterface $objectManager;

    /**
     * @var int
     */
    private int $storeId = 0;

    /**
     * Store the test's snippets and forget every kept selection
     *
     * @return void
     */
    protected function setUp(): void
    {
        $this->objectManager = Bootstrap::getObjectManager();
        $this->storeId = (int) $this->get(StoreManagerInterface::class)->getDefaultStoreView()?->getId();
        $this->get(RichSnippetsCache::class)->clean();
        $this->store('rs-test-product', true, ['product']);
        $this->store('rs-test-simple-only', true, ['product'], $this->all([
            $this->leaf('product_type', '!=', 'virtual'),
        ]));
        $this->store('rs-test-virtual-only', true, ['product'], $this->all([
            $this->leaf('product_type', '==', 'virtual'),
        ]));
        $this->store('rs-test-nested', true, ['product', 'cms'], [
            'aggregator' => 'any',
            'conditions' => [
                $this->all([
                    $this->leaf('product_type', '==', 'virtual'),
                    $this->leaf('product_attribute', '{}', 'VIRTUAL', 'sku'),
                ]),
                $this->leaf('product_attribute', '==', 'No such name', 'name'),
                $this->leaf('cms_page', '==', 'no-such-page'),
            ],
        ] + $this->all([]));
        $this->store('rs-test-weighed', true, ['product'], $this->all([
            $this->leaf('product_attribute', '>=', '1', 'weight'),
            $this->leaf('product_attribute', '==', '', 'name'),
        ]));
        $this->store('rs-test-switched-off', false, ['product']);
        $this->store('rs-test-other-store', true, ['product'], [], [$this->storeId + 1000]);
        $this->store('rs-test-organization', true, ['home']);
        $this->store('rs-test-everywhere', true, ['home', 'product', 'category', 'cms', 'other']);
        $policy = $this->get(SnippetFactoryInterface::class)->draft('rs-test-policy', 'Return policy', true);
        $policy->changeTemplate('{"@type": "MerchantReturnPolicy"}', $this->get(TemplateTypeInterface::class));
        $this->get(SnippetRootRepositoryInterface::class)->save($policy);
    }

    /**
     * @return void
     */
    protected function tearDown(): void
    {
        $this->get(RichSnippetsCache::class)->clean();
    }

    public function testAProductPageOutputsTheSnippetsWhoseConditionsItsProductMeets(): void
    {
        self::assertSame(
            ['rs-test-product', 'rs-test-simple-only', 'rs-test-weighed', 'rs-test-everywhere'],
            $this->selected($this->page('product', 'simple'))
        );
        self::assertSame(
            ['rs-test-product', 'rs-test-virtual-only', 'rs-test-nested', 'rs-test-everywhere'],
            $this->selected($this->page('product', 'virtual'))
        );
    }

    public function testEveryOtherPageOutputsOnlyWhatIsSetForIt(): void
    {
        self::assertSame(['rs-test-everywhere'], $this->selected($this->page('other', null)));
    }

    public function testEachPageSnippetNotOutputIsExplained(): void
    {
        self::assertSame(
            [
                ['rs-test-virtual-only', ExclusionReason::ConditionsNotMet, [
                    ['Product type', ['Virtual Product'], 'Simple Product'],
                ]],
                ['rs-test-nested', ExclusionReason::ConditionsNotMet, [
                    ['Product type', ['Virtual Product'], 'Simple Product'],
                    ['SKU', ['VIRTUAL'], $this->sku('simple')],
                    ['Product Name', ['No such name'], $this->productName('simple')],
                ]],
                ['rs-test-switched-off', ExclusionReason::SwitchedOff, []],
                ['rs-test-other-store', ExclusionReason::OtherStores, []],
                ['rs-test-organization', ExclusionReason::OtherKinds, []],
            ],
            $this->explained($this->page('product', 'simple'))
        );
    }

    /**
     * Store a page snippet
     *
     * @param string $code
     * @param bool $active
     * @param list<string> $kinds
     * @param array<string, mixed> $tree the decoded builder tree; none when empty
     * @param list<int> $storeIds
     * @return void
     */
    private function store(
        string $code,
        bool $active,
        array $kinds,
        array $tree = [],
        array $storeIds = [0]
    ): void {
        $snippet = $this->get(SnippetFactoryInterface::class)->draft($code, 'Test ' . $code, false);
        $snippet->changeTemplate(self::TEMPLATE, $this->get(TemplateTypeInterface::class));
        $snippet->place(
            $kinds,
            $this->get(ConditionTreeParserInterface::class)->parseDecoded($tree),
            $storeIds,
            $this->get(PlacementVocabularyInterface::class)
        );
        if ($active) {
            $snippet->switchOn();
        }
        $this->get(SnippetRootRepositoryInterface::class)->save($snippet);
    }

    /**
     * The codes of the test's snippets a page outputs
     *
     * @param PageEntityInterface $page
     * @return list<string>
     */
    private function selected(PageEntityInterface $page): array
    {
        return $this->testCodes(array_map(
            static fn (SnippetInterface $snippet): string => $snippet->getCode(),
            $this->get(SnippetSelectorInterface::class)->select($page)
        ));
    }

    /**
     * The test's snippets a page does not output, as code, reason and mismatches
     *
     * @param PageEntityInterface $page
     * @return list<array{string, ExclusionReason, list<array{string, list<string>, string|null}>}>
     */
    private function explained(PageEntityInterface $page): array
    {
        $explained = [];
        foreach ($this->get(SnippetSelectorInterface::class)->explain($page) as $exclusion) {
            if (!str_starts_with($exclusion->getSnippet()->getCode(), 'rs-test-')) {
                continue;
            }
            $explained[] = [
                $exclusion->getSnippet()->getCode(),
                $exclusion->getReason(),
                $this->mismatches($exclusion),
            ];
        }

        return $explained;
    }

    /**
     * @param SnippetExclusionInterface $exclusion
     * @return list<array{string, list<string>, string|null}>
     */
    private function mismatches(SnippetExclusionInterface $exclusion): array
    {
        return array_map(
            static fn (ConditionMismatchInterface $mismatch): array => [
                $mismatch->getFieldLabel(),
                $mismatch->getValueLabels(),
                $mismatch->getPageValue(),
            ],
            $exclusion->getMismatches()
        );
    }

    /**
     * @param list<string> $codes
     * @return list<string>
     */
    private function testCodes(array $codes): array
    {
        return array_values(array_filter(
            $codes,
            static fn (string $code): bool => str_starts_with($code, 'rs-test-')
        ));
    }

    /**
     * An all-group of the builder's tree
     *
     * @param list<array<string, mixed>> $conditions
     * @return array<string, mixed>
     */
    private function all(array $conditions): array
    {
        return [
            'type' => 'rich_snippets/combine',
            'attribute' => null,
            'operator' => null,
            'value' => '1',
            'is_value_processed' => null,
            'aggregator' => 'all',
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
     * The SKU of a fixture product
     *
     * @param string $fixture
     * @return string
     */
    private function sku(string $fixture): string
    {
        $product = DataFixtureStorageManager::getStorage()->get($fixture);
        self::assertInstanceOf(AbstractModel::class, $product);

        $sku = $product->getData('sku');
        self::assertIsString($sku);

        return $sku;
    }

    /**
     * The name of a fixture product
     *
     * @param string $fixture
     * @return string
     */
    private function productName(string $fixture): string
    {
        $product = DataFixtureStorageManager::getStorage()->get($fixture);
        self::assertInstanceOf(AbstractModel::class, $product);

        $name = $product->getData('name');
        self::assertIsString($name);

        return $name;
    }

    /**
     * A page of a kind in the default store view, showing a fixture entity or none
     *
     * @param string $kind
     * @param string|null $fixture
     * @return PageEntityInterface
     */
    private function page(string $kind, ?string $fixture): PageEntityInterface
    {
        $id = null;
        if ($fixture !== null) {
            $entity = DataFixtureStorageManager::getStorage()->get($fixture);
            self::assertInstanceOf(AbstractModel::class, $entity);
            $id = $entity->getId();
            self::assertIsNumeric($id);
            $id = (int) $id;
        }

        return $this->get(PageEntityFactoryInterface::class)->create($kind, $id, $this->storeId);
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
