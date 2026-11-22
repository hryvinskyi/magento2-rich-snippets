<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Test\Integration\Model;

use Hryvinskyi\JsonTemplate\Api\Exception\CompileException;
use Hryvinskyi\RichSnippets\Api\CodeAllocatorInterface;
use Hryvinskyi\RichSnippets\Api\ConditionTreeParserInterface;
use Hryvinskyi\RichSnippets\Api\ConditionTreePrinterInterface;
use Hryvinskyi\RichSnippets\Api\Data\ConditionTreeInterface;
use Hryvinskyi\RichSnippets\Api\Data\OriginInterface;
use Hryvinskyi\RichSnippets\Api\Data\SnippetInterface;
use Hryvinskyi\RichSnippets\Api\Exception\SnippetInUseException;
use Hryvinskyi\RichSnippets\Api\Exception\SnippetRuleException;
use Hryvinskyi\RichSnippets\Api\Exception\SnippetRuleReason;
use Hryvinskyi\RichSnippets\Api\OriginFactoryInterface;
use Hryvinskyi\RichSnippets\Api\PlacementVocabularyInterface;
use Hryvinskyi\RichSnippets\Api\SnippetRepositoryInterface;
use Hryvinskyi\RichSnippets\Model\Snippet\SnippetFactoryInterface;
use Hryvinskyi\RichSnippets\Model\Snippet\SnippetRootInterface;
use Hryvinskyi\RichSnippets\Model\Snippet\SnippetRootRepositoryInterface;
use Hryvinskyi\RichSnippets\Model\Snippet\TemplateTypeInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * Snippets saved, read, listed and deleted through the repository the container builds, against the database
 */
#[DbIsolation(true)]
class SnippetRepositoryTest extends TestCase
{
    private const CONDITIONS = '{"type":"rich_snippets/combine","attribute":null,"operator":null,"value":"1",'
        . '"is_value_processed":null,"aggregator":"any","conditions":[{"type":"product_type","attribute":null,'
        . '"operator":"!=","value":"configurable","is_value_processed":false},{"type":"rich_snippets/combine",'
        . '"attribute":null,"operator":null,"value":"1","is_value_processed":null,"aggregator":"all","conditions":['
        . '{"type":"category_page","attribute":null,"operator":"()","value":"3,4","is_value_processed":null},'
        . '{"type":"product_type","attribute":null,"operator":"==","value":"","is_value_processed":null}]}]}';

    private const LIVE_STORE = 1;
    private const DELETED_STORE = 987654;

    private const PRODUCT_TEMPLATE = '{"@context": "https://schema.org", "@type": "Product",'
        . ' "name": "{{ product.name }}", "hasMerchantReturnPolicy": {"@include": "return-policy"}}';
    private const POLICY_TEMPLATE = '{"@type": "MerchantReturnPolicy", "returnPolicyCategory":'
        . ' "https://schema.org/MerchantReturnFiniteReturnWindow", "merchantReturnDays": 30}';

    /**
     * @var ObjectManagerInterface
     */
    private ObjectManagerInterface $objectManager;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $this->objectManager = Bootstrap::getObjectManager();
    }

    public function testASavedSnippetReadsBackWithEveryValue(): void
    {
        $snippet = $this->draft('product', 'Product', self::PRODUCT_TEMPLATE);
        $snippet->place(
            ['product', 'category'],
            $this->get(ConditionTreeParserInterface::class)->parse(self::CONDITIONS),
            [1],
            $this->get(PlacementVocabularyInterface::class)
        );
        $snippet->switchOn();
        $snippet->moveTo(20);
        $snippet->recordOrigin($this->get(OriginFactoryInterface::class)->create('product', 3));

        $saved = $this->repository()->save($snippet);
        $read = $this->repository()->get((int) $saved->getId());

        self::assertNotNull($saved->getId());
        self::assertSame('product', $read->getCode());
        self::assertSame('Product', $read->getName());
        self::assertSame('Product', $read->getType());
        self::assertSame(self::PRODUCT_TEMPLATE, $read->getTemplate());
        self::assertFalse($read->isBlock());
        self::assertTrue($read->isActive());
        self::assertSame(['product', 'category'], $read->getPageKinds());
        $printer = $this->get(ConditionTreePrinterInterface::class);
        self::assertSame(self::CONDITIONS, $printer->print($read->getConditions()));
        self::assertCount(3, $read->getConditions()->getLeaves());
        self::assertTrue($read->getConditions()->hasNestedGroup());
        self::assertSame([1], $read->getStoreIds());
        self::assertSame(20, $read->getSortOrder());
        self::assertSame('product', $read->getOrigin()?->getCode());
        self::assertSame(3, $read->getOrigin()->getVersion());
        self::assertNotNull($read->getUpdatedAt());
        self::assertSame('', $read->getUpdatedBy());
        self::assertSame((int) $saved->getId(), $this->repository()->getByCode('product')->getId());
    }

    public function testSavingAgainWritesOverTheStoredSnippet(): void
    {
        $saved = $this->rootRepository()->save($this->draft('product', 'Product', self::PRODUCT_TEMPLATE));
        $snippet = $this->rootRepository()->get((int) $saved->getId());
        $snippet->rename('Product, with offers');

        $this->rootRepository()->save($snippet);

        self::assertSame('Product, with offers', $this->repository()->get((int) $saved->getId())->getName());
        self::assertCount(1, $this->repository()->list(block: false));
    }

    public function testTheTypeIsTakenFromTheTemplatesRoot(): void
    {
        $snippet = $this->draft('organization', 'Organization', '{"@type": ["Organization", "Store"]}');
        $stale = $this->get(SnippetFactoryInterface::class)->restore(
            null,
            'organization',
            'Organization',
            '{"@type": ["Organization", "Store"]}',
            'Product',
            false,
            false,
            [],
            $this->noConditions(),
            [0],
            null,
            0,
            null,
            ''
        );

        self::assertSame('Organization', $this->repository()->save($stale)->getType());
        self::assertSame('Organization', $snippet->getType());
    }

    /**
     * A snippet a caller builds itself, with no ID and no store view, is not taken for a stored one that lost them all
     *
     * @return void
     */
    public function testANewSnippetSetForNoStoreViewIsRefusedAndNothingIsStored(): void
    {
        $draft = $this->draft('product', 'Product', self::PRODUCT_TEMPLATE);
        $noStoreView = new class ($draft) implements SnippetInterface {
            /**
             * @param SnippetInterface $snippet
             */
            public function __construct(private readonly SnippetInterface $snippet)
            {
            }

            public function getId(): ?int
            {
                return null;
            }

            public function getCode(): string
            {
                return $this->snippet->getCode();
            }

            public function getName(): string
            {
                return $this->snippet->getName();
            }

            public function getType(): string
            {
                return $this->snippet->getType();
            }

            public function isBlock(): bool
            {
                return false;
            }

            public function isActive(): bool
            {
                return false;
            }

            public function getPageKinds(): array
            {
                return ['product'];
            }

            public function getConditions(): ConditionTreeInterface
            {
                return $this->snippet->getConditions();
            }

            public function getStoreIds(): array
            {
                return [];
            }

            public function getTemplate(): string
            {
                return $this->snippet->getTemplate();
            }

            public function getOrigin(): ?OriginInterface
            {
                return null;
            }

            public function getSortOrder(): int
            {
                return 0;
            }

            public function getUpdatedAt(): ?string
            {
                return null;
            }

            public function getUpdatedBy(): string
            {
                return '';
            }
        };

        try {
            $this->repository()->save($noStoreView);
            self::fail('A new snippet set for no store view was stored.');
        } catch (SnippetRuleException $e) {
            self::assertSame(SnippetRuleReason::StoresMissing, $e->getReason());
        }

        self::assertSame([], $this->repository()->list());
    }

    public function testATemplateThatDoesNotCompileIsRefusedAndNothingIsStored(): void
    {
        try {
            $this->repository()->save($this->draft('product', 'Product', '{"@type": "Product", "name": "{{ }}"}'));
            self::fail('The template was accepted.');
        } catch (CompileException $e) {
            self::assertNotSame([], $e->getErrors());
        }

        self::assertSame([], $this->repository()->list());
    }

    public function testATemplateWithoutATypeIsRefused(): void
    {
        $this->expectException(SnippetRuleException::class);
        $this->expectExceptionMessage(SnippetRuleReason::TypeMissing->value);

        $this->repository()->save($this->draft('product', 'Product', '{"name": "{{ product.name }}"}'));
    }

    public function testASnippetThatWouldIncludeItselfThroughAnotherIsRefused(): void
    {
        $this->repository()->save($this->draft('product', 'Product', self::PRODUCT_TEMPLATE));
        $policy = $this->draft(
            'return-policy',
            'Return policy',
            '{"@type": "MerchantReturnPolicy", "itemCondition": {"@include": "product"}}'
        );

        try {
            $this->repository()->save($policy);
            self::fail('The loop was accepted.');
        } catch (SnippetRuleException $e) {
            self::assertSame(SnippetRuleReason::IncludeLoop, $e->getReason());
            self::assertSame(['codes' => 'return-policy → product → return-policy'], $e->getParams());
        }
        $this->expectException(NoSuchEntityException::class);
        $this->repository()->getByCode('return-policy');
    }

    public function testASnippetCannotIncludeItself(): void
    {
        $this->expectException(SnippetRuleException::class);
        $this->expectExceptionMessage('codes=faq → faq');

        $this->repository()->save($this->draft('faq', 'FAQ', '{"@type": "FAQPage", "x": {"@include": "faq"}}'));
    }

    public function testAStoredSnippetKeepsItsCode(): void
    {
        $saved = $this->repository()->save($this->draft('product', 'Product', self::PRODUCT_TEMPLATE));
        $renamed = $this->get(SnippetFactoryInterface::class)->restore(
            $saved->getId(),
            'product-renamed',
            'Product',
            self::PRODUCT_TEMPLATE,
            'Product',
            false,
            false,
            [],
            $this->noConditions(),
            [0],
            null,
            0,
            null,
            ''
        );

        $this->expectException(SnippetRuleException::class);
        $this->expectExceptionMessage(SnippetRuleReason::CodeChanged->value);
        $this->repository()->save($renamed);
    }

    public function testUpdatingAStoredSnippetToNoStoreViewIsRefusedAndKeepsItsStoreViews(): void
    {
        $stored = $this->repository()->save($this->placed('product', [self::LIVE_STORE]));

        try {
            $this->repository()->save($this->placed('product', [], (int) $stored->getId()));
            self::fail('A save took a snippet from a store view the shop has to none.');
        } catch (SnippetRuleException $e) {
            self::assertSame(SnippetRuleReason::StoresMissing, $e->getReason());
        }

        self::assertSame([self::LIVE_STORE], $this->repository()->get((int) $stored->getId())->getStoreIds());
    }

    public function testASnippetSetForAStoreViewTheShopHasAndOneItDoesNotIsNotLeftWithNone(): void
    {
        $stored = $this->repository()->save($this->placed('product', [self::LIVE_STORE, self::DELETED_STORE]));

        try {
            $this->repository()->save($this->placed('product', [], (int) $stored->getId()));
            self::fail('A save left a snippet with none while one of its store views still exists.');
        } catch (SnippetRuleException $e) {
            self::assertSame(SnippetRuleReason::StoresMissing, $e->getReason());
        }

        self::assertSame(
            [self::LIVE_STORE, self::DELETED_STORE],
            $this->repository()->get((int) $stored->getId())->getStoreIds()
        );
    }

    public function testASnippetSetForDeletedStoreViewsAloneMayLeaveThemAndThenBeSavedWithNone(): void
    {
        $stored = $this->repository()->save($this->placed('product', [self::DELETED_STORE]));
        $id = (int) $stored->getId();

        $forgotten = $this->rootRepository()->get($id);
        $forgotten->leaveStoreView(self::DELETED_STORE);
        $this->rootRepository()->save($forgotten);
        self::assertSame([], $this->repository()->get($id)->getStoreIds());

        $renamed = $this->rootRepository()->get($id);
        $renamed->rename('Product, renamed');
        $this->rootRepository()->save($renamed);

        $again = $this->repository()->get($id);
        self::assertSame('Product, renamed', $again->getName());
        self::assertSame([], $again->getStoreIds());
        self::assertFalse($again->isActive());
    }

    public function testASnippetSetForEveryStoreViewCannotBeUpdatedToNone(): void
    {
        $stored = $this->repository()->save($this->placed('product', [SnippetInterface::ALL_STORES]));

        $this->expectException(SnippetRuleException::class);
        $this->expectExceptionMessage(SnippetRuleReason::StoresMissing->value);
        $this->repository()->save($this->placed('product', [], (int) $stored->getId()));
    }

    public function testASecondSnippetWithATakenCodeIsRefused(): void
    {
        $this->repository()->save($this->draft('product', 'Product', self::PRODUCT_TEMPLATE));

        $this->expectException(SnippetRuleException::class);
        $this->expectExceptionMessage(SnippetRuleReason::CodeTaken->value);
        $this->repository()->save($this->draft('product', 'Product 2', self::PRODUCT_TEMPLATE));
    }

    public function testTheAllocatorHandsOutTheNextFreeCodeOfAName(): void
    {
        $allocator = $this->get(CodeAllocatorInterface::class);
        self::assertSame('product', $allocator->allocate('Product'));
        $this->repository()->save($this->draft('product', 'Product', self::PRODUCT_TEMPLATE));
        $this->repository()->save($this->draft('product-2', 'Product', self::PRODUCT_TEMPLATE));

        self::assertSame('product-3', $allocator->allocate('Product'));
        self::assertSame('new-snippet', $allocator->allocate('New snippet'));
        self::assertSame('ruckgabe-bedingungen', $allocator->allocate('Rückgabe & Bedingungen'));
    }

    public function testListingFiltersByStoreKindSwitchAndBlock(): void
    {
        $vocabulary = $this->get(PlacementVocabularyInterface::class);
        $everywhere = $this->draft('organization', 'Organization', '{"@type": "Organization"}');
        $everywhere->place(['home', 'other'], $this->noConditions(), [0], $vocabulary);
        $everywhere->switchOn();
        $secondStore = $this->draft('product', 'Product', self::PRODUCT_TEMPLATE);
        $secondStore->place(['product'], $this->noConditions(), [2], $vocabulary);
        $block = $this->draft('return-policy', 'Return policy', self::POLICY_TEMPLATE, true);
        foreach ([$everywhere, $secondStore, $block] as $snippet) {
            $this->repository()->save($snippet);
        }

        self::assertSame(['organization', 'return-policy'], $this->codes($this->repository()->list(storeId: 1)));
        self::assertSame(
            ['organization', 'product', 'return-policy'],
            $this->codes($this->repository()->list(storeId: 2))
        );
        self::assertSame(['product'], $this->codes($this->repository()->list(pageKind: 'product')));
        self::assertSame(['organization'], $this->codes($this->repository()->list(active: true)));
        self::assertSame(['return-policy'], $this->codes($this->repository()->list(block: true)));
        self::assertSame([], $this->codes($this->repository()->list(storeId: 1, pageKind: 'product')));
    }

    public function testDeletingAnIncludedSnippetIsRefusedWithTheNamesOfItsIncluders(): void
    {
        $policy = $this->repository()->save(
            $this->draft('return-policy', 'Return policy', self::POLICY_TEMPLATE, true)
        );
        $this->repository()->save($this->draft('product', 'Product', self::PRODUCT_TEMPLATE));

        try {
            $this->repository()->delete($policy);
            self::fail('The included snippet was deleted.');
        } catch (SnippetInUseException $e) {
            self::assertSame('return-policy', $e->getSnippetCode());
            self::assertSame(['product' => 'Product'], $e->getIncluders());
        }
        self::assertSame('return-policy', $this->repository()->getByCode('return-policy')->getCode());
    }

    public function testDeletingASnippetNoOtherIncludesRemovesIt(): void
    {
        $product = $this->repository()->save($this->draft('product', 'Product', self::PRODUCT_TEMPLATE));

        $this->repository()->delete($product);

        $this->expectException(NoSuchEntityException::class);
        $this->repository()->get((int) $product->getId());
    }

    public function testAnUnknownIdIsNoSuchSnippet(): void
    {
        $this->expectException(NoSuchEntityException::class);
        $this->expectExceptionMessage('The snippet with ID "987654" does not exist.');

        $this->repository()->get(987654);
    }

    /**
     * A new snippet with a template
     *
     * @param string $code
     * @param string $name
     * @param string $template
     * @param bool $isBlock
     * @return SnippetRootInterface
     */
    private function draft(string $code, string $name, string $template, bool $isBlock = false): SnippetRootInterface
    {
        $snippet = $this->get(SnippetFactoryInterface::class)->draft($code, $name, $isBlock);
        $snippet->changeTemplate($template, $this->get(TemplateTypeInterface::class));

        return $snippet;
    }

    /**
     * A switched-off product snippet as a caller hands it in, set for the given store views
     *
     * @param string $code
     * @param list<int> $storeIds
     * @param int|null $id the stored snippet it is saved over
     * @return SnippetInterface
     */
    private function placed(string $code, array $storeIds, ?int $id = null): SnippetInterface
    {
        return $this->get(SnippetFactoryInterface::class)->restore(
            $id,
            $code,
            'Product',
            self::PRODUCT_TEMPLATE,
            'Product',
            false,
            false,
            ['product'],
            $this->noConditions(),
            $storeIds,
            null,
            0,
            null,
            ''
        );
    }

    /**
     * The codes of snippets, in their order
     *
     * @param list<SnippetInterface> $snippets
     * @return list<string>
     */
    private function codes(array $snippets): array
    {
        return array_map(static fn (SnippetInterface $snippet): string => $snippet->getCode(), $snippets);
    }

    /**
     * @return SnippetRepositoryInterface
     */
    private function repository(): SnippetRepositoryInterface
    {
        return $this->get(SnippetRepositoryInterface::class);
    }

    /**
     * @return SnippetRootRepositoryInterface
     */
    private function rootRepository(): SnippetRootRepositoryInterface
    {
        return $this->get(SnippetRootRepositoryInterface::class);
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

    /**
     * No conditions
     *
     * @return ConditionTreeInterface
     */
    private function noConditions(): ConditionTreeInterface
    {
        return $this->get(ConditionTreeParserInterface::class)->parse('');
    }
}
