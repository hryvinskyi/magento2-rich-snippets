<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Test\Integration\Controller\Adminhtml\Snippets;

use Hryvinskyi\RichSnippets\Api\Data\SnippetInterface;
use Hryvinskyi\RichSnippets\Api\SnippetRepositoryInterface;
use Hryvinskyi\RichSnippets\Model\Admin\AdminResources;
use Hryvinskyi\RichSnippets\Test\Integration\Fixture\AsksAsTheSnippetsPage;
use Hryvinskyi\RichSnippets\Test\Integration\Fixture\StoresTestSnippets;
use Laminas\Http\Header\HeaderInterface;
use Magento\Catalog\Test\Fixture\SelectAttribute as SelectAttributeFixture;
use Magento\Eav\Model\Config as EavConfig;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\StoreManagerInterface;
use Magento\TestFramework\Fixture\AppArea;
use Magento\TestFramework\Fixture\AppIsolation;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Fixture\DataFixtureStorageManager;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\TestCase\AbstractBackendController;

/**
 * Export and import through the real admin, as the Snippets page sends them
 *
 * An export is a file named after the snippet or the shop, with store views by code and the conditions tree with the
 * label of each value; an export imported into the same shop is identical throughout. A file from another shop is
 * resolved by store code and value label, and whatever cannot be resolved is reported and keeps the snippet from
 * being added. A commit
 * replaces, adds and keeps both as chosen, writes once per idempotency key, and refuses a loop the file would close —
 * in the preview when the file closes it whatever is chosen, at the commit when only the choices close it. The
 * parent class adds the access tests for the permission to change snippets, on the preview.
 */
#[AppArea('adminhtml'), AppIsolation(true), DbIsolation(true)]
class SnippetTransferEndpointTest extends AbstractBackendController
{
    use AsksAsTheSnippetsPage;
    use StoresTestSnippets;

    private const EXPORT = 'backend/richsnippets/snippets/export';
    private const PREVIEW = 'backend/richsnippets/snippets/importPreview';
    private const COMMIT = 'backend/richsnippets/snippets/importCommit';

    /**
     * @var string
     */
    protected $resource = AdminResources::SNIPPETS;

    /**
     * @var string
     */
    protected $uri = self::PREVIEW;

    /**
     * @var string
     */
    protected $httpMethod = HttpRequest::METHOD_POST;

    /**
     * Send requests the way the browser does, the parent's access tests included
     *
     * @inheritDoc
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->askAsTheBrowser(HttpRequest::METHOD_POST, ['file' => '{}']);
    }

    #[DataFixture(
        SelectAttributeFixture::class,
        ['attribute_code' => 'rs_tr_colour', 'default_frontend_label' => 'Colour', 'options' => ['Tan', 'Olive']],
        as: 'colour'
    )]
    public function testAnExportOfOneSnippetIsAFileWithStoreCodesAndTheConditionsTreeWithItsLabels(): void
    {
        $snippet = $this->storedProduct('rs-product', 'Product with variants', false, [
            $this->leaf('attribute_set', '==', (string) $this->defaultAttributeSetId()),
            $this->combine('any', [
                $this->leaf('product_type', '!=', 'configurable'),
                $this->leaf(
                    'product_attribute',
                    '()',
                    $this->option('Tan') . ',' . $this->option('Olive'),
                    'rs_tr_colour'
                ),
            ]),
        ]);

        $document = $this->read(self::EXPORT, ['id' => (string) $snippet->getId()]);

        self::assertSame('attachment; filename="product-with-variants.json"', $this->disposition());
        self::assertSame(1, $this->at($document, 'hryvinskyi_rich_snippets', 'version'));
        self::assertIsString($this->at($document, 'hryvinskyi_rich_snippets', 'exportedAt'));
        self::assertIsString($this->at($document, 'hryvinskyi_rich_snippets', 'source'));
        self::assertCount(1, (array) $this->at($document, 'snippets'));
        self::assertSame('rs-product', $this->at($document, 'snippets', 0, 'code'));
        self::assertSame([$this->defaultStoreCode()], $this->at($document, 'snippets', 0, 'stores'));
        self::assertSame(
            $this->combine('all', [
                $this->leaf('attribute_set', '==', (string) $this->defaultAttributeSetId()) + ['labels' => ['Default']],
                $this->combine('any', [
                    $this->leaf('product_type', '!=', 'configurable') + ['labels' => ['Configurable Product']],
                    $this->leaf(
                        'product_attribute',
                        '()',
                        $this->option('Tan') . ',' . $this->option('Olive'),
                        'rs_tr_colour'
                    ) + ['labels' => ['Tan', 'Olive']],
                ]),
            ]),
            $this->at($document, 'snippets', 0, 'conditions')
        );
        self::assertSame($snippet->getTemplate(), $this->at($document, 'snippets', 0, 'template'));
        $plain = $this->storeSnippet('rs-plain', 'Plain', ['@type' => 'Thing'], ['kinds' => ['home']]);
        $written = $this->at($this->read(self::EXPORT, ['id' => (string) $plain->getId()]), 'snippets', 0);
        self::assertIsArray($written);
        self::assertArrayHasKey('conditions', $written);
        self::assertNull($written['conditions'], 'No conditions are written as null.');
    }

    public function testAnExportOfEverySnippetImportedIntoTheSameShopIsIdenticalThroughout(): void
    {
        $this->storeSnippet('rs-policy', 'Return policy', ['@type' => 'MerchantReturnPolicy'], ['block' => true]);
        $this->storedProduct('rs-product', 'Product');

        $this->read(self::EXPORT);
        $file = (string) $this->httpResponse()->getBody();
        $disposition = $this->disposition();
        $preview = $this->post(self::PREVIEW, ['file' => $file]);

        self::assertMatchesRegularExpression('/^attachment; filename="rich-snippets[a-z0-9-]*\.json"$/', $disposition);
        self::assertSame(count($this->repository()->list()), $preview['count']);
        foreach ((array) $preview['snippets'] as $entry) {
            self::assertIsArray($entry);
            self::assertSame('identical', $entry['status'], (string) json_encode($entry));
            self::assertSame([], $entry['choices']);
            self::assertSame([], $entry['problems']);
        }
    }

    #[DataFixture(
        SelectAttributeFixture::class,
        ['attribute_code' => 'rs_tr_colour', 'default_frontend_label' => 'Colour', 'options' => ['Tan', 'Olive']],
        as: 'colour'
    )]
    public function testAStoreViewAndAValueLabelThisShopLacksAreReportedAndKeepTheSnippetFromBeingAdded(): void
    {
        $resolvable = $this->combine('all', [
            $this->leaf('attribute_set', '==', '999') + ['labels' => ['Default']],
            $this->combine('any', [
                $this->leaf('product_attribute', '()', '901,902', 'rs_tr_colour') + ['labels' => ['Olive', 'Tan']],
                $this->leaf('product_type', '==', 'virtual') + ['labels' => ['Virtual Product']],
            ]),
        ]);
        $preview = $this->post(self::PREVIEW, ['file' => $this->file([
            $this->entry('rs-unknown', [
                'stores' => [$this->defaultStoreCode(), 'trade'],
                'conditions' => $this->combine('all', [
                    $this->leaf('attribute_set', '==', '999') + ['labels' => ['Leather goods']],
                    $this->leaf('product_attribute', '()', '901', 'rs_tr_colour') + ['labels' => ['Saffron']],
                ]),
            ]),
            $this->entry('rs-resolved', [
                'stores' => ['trade', $this->defaultStoreCode()],
                'conditions' => $resolvable,
            ]),
            $this->entry('rs-storeless', ['stores' => ['trade']]),
            $this->entry('rs-unreadable', ['conditions' => $this->combine('all', [
                $this->leaf('product_attribute', '==', 'x', 'rs_no_such_attribute'),
            ])]),
        ])]);

        $entries = array_column((array) $preview['snippets'], null, 'code');
        self::assertSame('new', $this->at($entries, 'rs-unknown', 'status'));
        self::assertSame(['skip'], $this->at($entries, 'rs-unknown', 'choices'));
        self::assertSame([
            ['reason' => 'store_unknown', 'params' => ['store' => 'trade']],
            [
                'reason' => 'condition_unresolved',
                'params' => [
                    'field' => 'attribute_set',
                    'attribute' => '',
                    'value' => '999',
                    'label' => 'Leather goods',
                ],
            ],
            [
                'reason' => 'condition_unresolved',
                'params' => [
                    'field' => 'product_attribute',
                    'attribute' => 'rs_tr_colour',
                    'value' => '901',
                    'label' => 'Saffron',
                ],
            ],
        ], $this->at($entries, 'rs-unknown', 'problems'));
        self::assertSame(['add', 'skip'], $this->at($entries, 'rs-resolved', 'choices'));
        self::assertSame(
            'Attribute set is Default and (Colour is one of Olive, Tan or Product type is Virtual Product)',
            $this->at($entries, 'rs-resolved', 'conditionSummary')
        );
        self::assertSame(['skip'], $this->at($entries, 'rs-storeless', 'choices'));
        self::assertSame('invalid', $this->at($entries, 'rs-unreadable', 'status'));
        self::assertSame(
            [[
                'reason' => 'rule_broken',
                'params' => [
                    'rule' => 'condition_attribute_unknown',
                    'field' => 'product_attribute',
                    'attribute' => 'rs_no_such_attribute',
                ],
            ]],
            $this->at($entries, 'rs-unreadable', 'problems')
        );

        $commit = $this->post(self::COMMIT, [
            'file' => $this->file([$this->entry('rs-resolved', [
                'stores' => ['trade', $this->defaultStoreCode()],
                'conditions' => $resolvable,
            ])]),
            'choices' => ['rs-resolved' => 'add'],
            'idempotencyKey' => 'rs-import-resolved',
        ]);

        self::assertSame(1, $commit['count']);
        $added = $this->repository()->getByCode('rs-resolved');
        self::assertSame([$this->defaultStoreId()], $added->getStoreIds());
        self::assertSame(
            [
                ['attribute_set', null, (string) $this->defaultAttributeSetId()],
                ['product_attribute', 'rs_tr_colour', $this->option('Olive') . ',' . $this->option('Tan')],
                ['product_type', null, 'virtual'],
            ],
            array_map(
                static fn ($leaf): array => [$leaf->getField(), $leaf->getAttribute(), $leaf->getValue()],
                $added->getConditions()->getLeaves()
            )
        );
    }

    public function testACommitReplacesKeepingTheSwitchStoreViewsAndPlaceAndAddsSwitchedOff(): void
    {
        $stored = $this->storedProduct('rs-product', 'Product', true);

        $commit = $this->post(self::COMMIT, [
            'file' => $this->file([
                $this->entry('rs-product', ['name' => 'Product v2', 'stores' => ['*'], 'sortOrder' => 1]),
                $this->entry('rs-gift', ['name' => 'Gift card', 'active' => true]),
            ]),
            'choices' => ['rs-product' => 'replace', 'rs-gift' => 'add'],
            'idempotencyKey' => 'rs-import-replace',
        ]);

        self::assertSame(
            [['rs-product', 'replace'], ['rs-gift', 'add']],
            array_map(
                static fn (mixed $record): array => is_array($record) ? [$record['code'], $record['choice']] : [],
                (array) $commit['imported']
            )
        );
        $replaced = $this->repository()->getByCode('rs-product');
        self::assertSame((int) $stored->getId(), $replaced->getId());
        self::assertSame('Product v2', $replaced->getName());
        self::assertTrue($replaced->isActive());
        self::assertSame($stored->getStoreIds(), $replaced->getStoreIds());
        self::assertSame($stored->getSortOrder(), $replaced->getSortOrder());
        self::assertTrue($replaced->getConditions()->isEmpty());
        $added = $this->repository()->getByCode('rs-gift');
        self::assertFalse($added->isActive());
        self::assertSame('Gift card', $added->getName());
    }

    public function testKeepBothAddsTheFileSnippetBesideTheShopsOneSwitchedOff(): void
    {
        $stored = $this->storedProduct('rs-product', 'Product', true);

        $commit = $this->post(self::COMMIT, [
            'file' => $this->file([$this->entry('rs-product', ['name' => 'Product', 'active' => true])]),
            'choices' => ['rs-product' => 'keep_both'],
            'idempotencyKey' => 'rs-import-both',
        ]);

        $code = $this->at($commit, 'imported', 0, 'code');
        self::assertIsString($code);
        self::assertNotSame('rs-product', $code);
        $copy = $this->repository()->getByCode($code);
        self::assertSame('Product (imported)', $copy->getName());
        self::assertFalse($copy->isActive());
        self::assertSame($stored->getTemplate(), $this->repository()->getByCode('rs-product')->getTemplate());
    }

    public function testACommitRepeatedWithItsKeyImportsOnceAndAnswersTheSame(): void
    {
        $body = [
            'file' => $this->file([$this->entry('rs-gift', ['name' => 'Gift card'])]),
            'choices' => ['rs-gift' => 'add'],
            'idempotencyKey' => 'rs-import-once',
        ];

        $first = $this->post(self::COMMIT, $body);
        $replay = $this->post(self::COMMIT, $body);

        self::assertSame($first['imported'], $replay['imported']);
        self::assertSame(
            ['Gift card'],
            array_values(array_map(
                static fn (SnippetInterface $snippet): string => $snippet->getName(),
                array_filter(
                    $this->repository()->list(),
                    static fn (SnippetInterface $snippet): bool => str_starts_with($snippet->getName(), 'Gift card')
                )
            ))
        );
    }

    public function testALoopTheFileClosesWhateverIsChosenIsRefusedInThePreviewAndTheCommit(): void
    {
        $this->storeSnippet('rs-a', 'A', ['@type' => 'Thing', 'part' => ['@include' => 'rs-b']], ['kinds' => ['home']]);
        $file = $this->file([$this->entry('rs-b', ['template' => '{"@type": "Thing", "of": {"@include": "rs-a"}}'])]);

        $preview = $this->post(self::PREVIEW, ['file' => $file]);
        $commit = $this->post(self::COMMIT, [
            'file' => $file,
            'choices' => ['rs-b' => 'add'],
            'idempotencyKey' => 'rs-import-loop',
        ], 400);

        self::assertSame('invalid', $this->at($preview, 'snippets', 0, 'status'));
        self::assertSame([], $this->at($preview, 'snippets', 0, 'choices'));
        self::assertSame(
            [['reason' => 'include_loop', 'params' => ['codes' => 'rs-b → rs-a → rs-b']]],
            $this->at($preview, 'snippets', 0, 'problems')
        );
        self::assertSame(['error' => 'choice_not_offered'], $commit);
        $this->assertNotStored('rs-b');
    }

    public function testALoopOnlyTheChoicesCloseIsRefusedAtTheCommit(): void
    {
        $this->storeSnippet('rs-x', 'X', ['@type' => 'Thing', 'part' => ['@include' => 'rs-y']], ['kinds' => ['home']]);
        $file = $this->file([
            $this->entry('rs-x', ['name' => 'X', 'pageKinds' => ['home'], 'template' => '{"@type": "Thing"}']),
            $this->entry('rs-y', ['template' => '{"@type": "Thing", "of": {"@include": "rs-x"}}']),
        ]);

        $preview = $this->post(self::PREVIEW, ['file' => $file]);
        $commit = $this->post(self::COMMIT, [
            'file' => $file,
            'choices' => ['rs-x' => 'skip', 'rs-y' => 'add'],
            'idempotencyKey' => 'rs-import-chosen-loop',
        ], 400);

        self::assertSame(['changed', 'new'], array_column((array) $preview['snippets'], 'status'));
        self::assertSame(['error' => 'include_loop'], $commit);
        $this->assertNotStored('rs-y');
    }

    public function testAFileThatIsNotAnExportAndAnExportOfASnippetThatIsGoneAnswer400(): void
    {
        self::assertSame(
            ['error' => 'not_an_export'],
            $this->post(self::PREVIEW, ['file' => '{"products": []}'], 400)
        );
        self::assertSame(['error' => 'unknown_snippet'], $this->read(self::EXPORT, ['id' => '987654'], 400));
    }

    /**
     * Store a product snippet for the default store view, on product pages of the default attribute set
     *
     * @param string $code
     * @param string $name
     * @param bool $active
     * @param list<array<string, mixed>>|null $conditions the outer group's conditions and groups; the default
     *     attribute set when null
     * @return SnippetInterface
     */
    private function storedProduct(
        string $code,
        string $name,
        bool $active = false,
        ?array $conditions = null
    ): SnippetInterface {
        $nodes = $conditions ?? [$this->leaf('attribute_set', '==', (string) $this->defaultAttributeSetId())];

        return $this->storeSnippet($code, $name, [
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => '{{product.name}}',
            'hasMerchantReturnPolicy' => ['@include' => 'rs-policy'],
        ], [
            'kinds' => ['product'],
            'tree' => $this->combine('all', $nodes),
            'stores' => [$this->defaultStoreId()],
            'active' => $active,
            'sortOrder' => 5,
        ]);
    }

    /**
     * A snippet of an import file, a product snippet for every store view unless told otherwise
     *
     * @param string $code
     * @param array<string, mixed> $members
     * @return array<string, mixed>
     */
    private function entry(string $code, array $members = []): array
    {
        return $members + [
            'code' => $code,
            'name' => ucfirst($code),
            'block' => false,
            'active' => false,
            'pageKinds' => ['product'],
            'conditions' => null,
            'stores' => ['*'],
            'sortOrder' => 0,
            'origin' => null,
            'updatedAt' => '2026-11-13 10:00:00',
            'template' => '{"@context": "https://schema.org", "@type": "Product", "name": "{{product.name}}"}',
        ];
    }

    /**
     * The text of an export file holding snippets
     *
     * @param list<array<string, mixed>> $snippets
     * @return string
     */
    private function file(array $snippets): string
    {
        return (string) json_encode([
            'hryvinskyi_rich_snippets' => [
                'version' => 1,
                'exportedAt' => '2026-11-13T10:00:00+00:00',
                'source' => 'staging.example.com',
            ],
            'snippets' => $snippets,
        ], JSON_THROW_ON_ERROR);
    }

    /**
     * A group of the builder's tree
     *
     * @param string $aggregator
     * @param list<array<mixed>> $conditions
     * @return array<string, mixed>
     */
    private function combine(string $aggregator, array $conditions): array
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
     * The ID of an option of the test's colour attribute
     *
     * @param string $label
     * @return string
     */
    private function option(string $label): string
    {
        $attribute = DataFixtureStorageManager::getStorage()->get('colour');
        self::assertNotNull($attribute);
        $id = $attribute->getData($label);
        self::assertTrue(is_int($id) || is_string($id), sprintf('The colour attribute has the option "%s".', $label));

        return (string) $id;
    }

    /**
     * Prove no snippet has a code
     *
     * @param string $code
     * @return void
     */
    private function assertNotStored(string $code): void
    {
        $stored = null;
        try {
            $stored = $this->repository()->getByCode($code);
        } catch (NoSuchEntityException) {
            $stored = null;
        }
        self::assertNull($stored, sprintf('A snippet "%s" was stored.', $code));
    }

    /**
     * The last answer's attachment header
     *
     * @return string
     */
    private function disposition(): string
    {
        $header = $this->httpResponse()->getHeader('Content-Disposition');
        self::assertInstanceOf(HeaderInterface::class, $header, 'The answer is offered as a file.');

        return $header->getFieldValue();
    }

    /**
     * @return int
     */
    private function defaultAttributeSetId(): int
    {
        $config = $this->testObjectManager()->get(EavConfig::class);
        self::assertInstanceOf(EavConfig::class, $config);

        return (int) $config->getEntityType('catalog_product')->getDefaultAttributeSetId();
    }

    /**
     * @return int
     */
    private function defaultStoreId(): int
    {
        return (int) $this->storeManager()->getDefaultStoreView()?->getId();
    }

    /**
     * @return string
     */
    private function defaultStoreCode(): string
    {
        return (string) $this->storeManager()->getDefaultStoreView()?->getCode();
    }

    /**
     * @return StoreManagerInterface
     */
    private function storeManager(): StoreManagerInterface
    {
        $storeManager = $this->testObjectManager()->get(StoreManagerInterface::class);
        self::assertInstanceOf(StoreManagerInterface::class, $storeManager);

        return $storeManager;
    }

    /**
     * @return SnippetRepositoryInterface
     */
    private function repository(): SnippetRepositoryInterface
    {
        $repository = $this->testObjectManager()->get(SnippetRepositoryInterface::class);
        self::assertInstanceOf(SnippetRepositoryInterface::class, $repository);

        return $repository;
    }
}
