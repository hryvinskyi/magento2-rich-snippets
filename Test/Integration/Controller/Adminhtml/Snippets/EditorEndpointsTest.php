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
use Magento\Catalog\Test\Fixture\Attribute as AttributeFixture;
use Magento\Catalog\Test\Fixture\Product as ProductFixture;
use Magento\Catalog\Test\Fixture\SelectAttribute as SelectAttributeFixture;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\Component\ComponentRegistrar;
use Magento\Framework\Model\AbstractModel;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Magento\TestFramework\Fixture\AppArea;
use Magento\TestFramework\Fixture\AppIsolation;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Fixture\DataFixtureStorageManager;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\TestCase\AbstractBackendController;

/**
 * The editor's endpoints through the real admin, as the editor's island sends them
 *
 * A save made from an older version than the stored one answers 409 and writes nothing; a new snippet saved twice
 * with one key is created once; a draft that breaks a rule is refused with every problem, addressed to its part. The
 * check addresses template errors by their JSON path. The pickers read the field catalog with a catalog attribute's
 * value on a sample product, the values of a condition field, the pages a placement reaches, the sample pages and
 * the snippets that can be included, flagging the one that would close a loop. Each answer has the shape of its
 * contract file. The parent class adds the access tests for the permission to change snippets, on the save.
 */
#[AppArea('adminhtml'), AppIsolation(true), DbIsolation(true)]
class EditorEndpointsTest extends AbstractBackendController
{
    use AsksAsTheSnippetsPage;
    use StoresTestSnippets;

    private const LOAD = 'backend/richsnippets/snippets/load';
    private const SAVE = 'backend/richsnippets/snippets/save';
    private const VALIDATE = 'backend/richsnippets/snippets/validate';
    private const FIELDS = 'backend/richsnippets/snippets/fields';
    private const BUILDER_CONFIG = 'backend/richsnippets/snippets/builderConfig';
    private const CONDITION_OPTIONS = 'backend/richsnippets/snippets/conditionOptions';
    private const PLACEMENT_COUNT = 'backend/richsnippets/snippets/placementCount';
    private const SAMPLES = 'backend/richsnippets/snippets/samples';
    private const INCLUDE_CANDIDATES = 'backend/richsnippets/snippets/includeCandidates';
    private const CONTRACTS = '/Test/_contract/admin-pages/';

    /**
     * @var string
     */
    protected $resource = AdminResources::SNIPPETS;

    /**
     * @var string
     */
    protected $uri = self::SAVE;

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
        $this->askAsTheBrowser(HttpRequest::METHOD_POST, ['snippet' => ['id' => 987654]]);
    }

    public function testASaveFromAnOlderVersionAnswersConflictAndWritesNothing(): void
    {
        $stored = $this->storeSnippet('rs-edit', 'Product', $this->product('{{product.name}}'), [
            'kinds' => ['product'],
        ]);
        $loaded = $this->read(self::LOAD, ['id' => (string) $stored->getId()]);
        $version = $this->at($loaded, 'snippet', 'updatedAt');
        self::assertIsString($version);

        $first = $this->post(self::SAVE, [
            'snippet' => $this->draft($stored, ['name' => 'Product, first edit']),
            'updatedAt' => $version,
        ]);
        $second = $this->post(self::SAVE, [
            'snippet' => $this->draft($stored, ['name' => 'Product, second edit']),
            'updatedAt' => $version,
        ], 409);

        self::assertSame([], $first['problems']);
        $saved = $this->at($first, 'snippet', 'updatedAt');
        self::assertIsString($saved);
        self::assertNotSame($version, $saved, 'Every save moves the update time, even within the same second.');
        self::assertSame($saved, $this->at($second, 'conflict', 'updatedAt'));
        self::assertIsString($this->at($second, 'conflict', 'message'));
        self::assertStringStartsWith('Someone else saved this snippet at ', $this->at($second, 'conflict', 'message'));
        self::assertSame('Product, first edit', $this->repository()->get((int) $stored->getId())->getName());
        $this->assertSameShape($this->contract('editor-save.json')['conflict'] ?? null, $second);
        $this->assertSameShape($this->contract('editor-save.json')['saved'] ?? null, $first);
    }

    public function testANewSnippetSavedTwiceWithOneKeyIsCreatedOnce(): void
    {
        $count = count($this->repository()->list());
        $body = [
            'snippet' => [
                'name' => 'Editor product',
                'template' => (string) json_encode($this->product('{{product.name}}')),
                'pageKinds' => ['product'],
                'storeIds' => [0],
            ],
            'idempotencyKey' => 'editor-create-1',
        ];

        $first = $this->post(self::SAVE, $body);
        $again = $this->post(self::SAVE, $body);

        self::assertIsInt($this->at($first, 'snippet', 'id'));
        self::assertSame($this->at($first, 'snippet', 'id'), $this->at($again, 'snippet', 'id'));
        self::assertSame('editor-product', $this->at($first, 'snippet', 'code'));
        self::assertFalse($this->at($first, 'snippet', 'active'), 'A new snippet waits to be switched on.');
        self::assertCount($count + 1, $this->repository()->list());
        self::assertSame(['error' => 'idempotency_key'], $this->post(self::SAVE, ['snippet' => $body['snippet']], 400));
    }

    public function testADraftThatBreaksARuleIsRefusedWithItsProblemsAndTheKeyStaysUnused(): void
    {
        $count = count($this->repository()->list());
        $body = [
            'snippet' => ['name' => 'Typeless', 'template' => '{"name": "{{product.name}}"}', 'pageKinds' => []],
            'idempotencyKey' => 'editor-refused-1',
        ];

        $refused = $this->post(self::SAVE, $body);
        $body['snippet']['template'] = (string) json_encode($this->product('{{product.name}}'));
        $saved = $this->post(self::SAVE, $body);

        self::assertNull($refused['snippet']);
        self::assertSame(
            [['part' => 'type', 'path' => '$', 'code' => 'type_missing']],
            $this->problemKeys($refused['problems'])
        );
        $this->assertSameShape($this->contract('editor-save.json')['refused'] ?? null, $refused);
        self::assertIsInt($this->at($saved, 'snippet', 'id'), 'The corrected draft is saved with the same key.');
        self::assertSame([], $saved['problems'], 'A snippet output nowhere is saved as a draft.');
        self::assertCount($count + 1, $this->repository()->list());
    }

    public function testTheCheckAddressesEachProblemToItsPartAndPath(): void
    {
        $answer = $this->post(self::VALIDATE, ['snippet' => [
            'name' => '',
            'template' => '{"@type": "Product", "name": "{{product.name | shout}}"}',
            'pageKinds' => [],
        ]]);

        self::assertSame(
            [
                ['part' => 'template', 'path' => '$.name', 'code' => 'unknown-filter'],
                ['part' => 'name', 'path' => '$', 'code' => 'name_invalid'],
                ['part' => 'pageKinds', 'path' => '$', 'code' => 'no_page_kinds'],
            ],
            $this->problemKeys($answer['problems'])
        );
        self::assertSame('There is no filter called shout.', $this->at($answer, 'problems', 0, 'message'));
        self::assertFalse($this->at($answer, 'problems', 2, 'blocking'));
        $this->assertSameShape($this->contract('editor-draft.json')['validate'] ?? null, $answer);
    }

    public function testADraftsConditionsAreSavedAsWrittenAndConditionsThatCannotStandAreTheirProblem(): void
    {
        $stored = $this->storeSnippet('rs-edit', 'Product', $this->product('{{product.name}}'), [
            'kinds' => ['product'],
        ]);
        $text = $this->conditions('any', [['product_type', '!=', 'configurable'], ['attribute_set', '()', '4,9']]);

        $saved = $this->post(self::SAVE, ['snippet' => $this->draft($stored, ['conditions' => $text])]);
        $outside = $this->post(self::VALIDATE, ['snippet' => $this->draft($stored, [
            'conditions' => $this->conditions('all', [['category_page', '()', '3']]),
        ])]);
        $unreadable = $this->post(self::VALIDATE, ['snippet' => $this->draft($stored, [
            'conditions' => $this->conditions('all', [['product_type', '{}', 'simple']]),
        ])]);

        self::assertSame([], $saved['problems']);
        self::assertSame($text, $this->at($saved, 'snippet', 'conditions'));
        self::assertSame(
            [['part' => 'conditions', 'path' => '$', 'code' => 'condition_field_outside_kinds']],
            $this->problemKeys($outside['problems'])
        );
        self::assertSame(
            [['part' => 'conditions', 'path' => '$', 'code' => 'condition_operator_unknown']],
            $this->problemKeys($unreadable['problems'])
        );
    }

    public function testTheCheckFindsALoopThroughTheSnippetsThatIncludeTheDraft(): void
    {
        $policy = $this->storeSnippet('rs-policy', 'Return policy', ['@type' => 'MerchantReturnPolicy'], [
            'block' => true,
        ]);
        $this->storeSnippet('rs-product', 'Product', $this->product('{{product.name}}', 'rs-policy'), [
            'kinds' => ['product'],
        ]);

        $answer = $this->post(self::VALIDATE, ['snippet' => $this->draft($policy, [
            'template' => '{"@type": "MerchantReturnPolicy", "x": {"@include": "rs-product"}}',
        ])]);

        self::assertSame(
            [['part' => 'template', 'path' => '$', 'code' => 'include_loop']],
            $this->problemKeys($answer['problems'])
        );
        self::assertSame(
            'This would make a loop: rs-policy → rs-product → rs-policy.',
            $this->at($answer, 'problems', 0, 'message')
        );
    }

    public function testTheIncludeCandidatesFlagTheSnippetThatWouldCloseALoop(): void
    {
        $policy = $this->storeSnippet('rs-policy', 'Return policy', ['@type' => 'MerchantReturnPolicy'], [
            'block' => true,
        ]);
        $product = $this->storeSnippet('rs-product', 'Product', $this->product('{{product.name}}', 'rs-policy'), [
            'kinds' => ['product'],
        ]);

        $forPolicy = $this->candidates($this->read(self::INCLUDE_CANDIDATES, ['id' => (string) $policy->getId()]));
        $forProduct = $this->candidates($this->read(self::INCLUDE_CANDIDATES, ['id' => (string) $product->getId()]));
        $forNew = $this->candidates($this->read(self::INCLUDE_CANDIDATES));

        self::assertTrue($forPolicy['rs-product']['loops'] ?? null, 'The product contains the policy.');
        self::assertArrayNotHasKey('rs-policy', $forPolicy, 'A snippet is no candidate of its own.');
        self::assertFalse($forProduct['rs-policy']['loops'] ?? null);
        self::assertTrue($forProduct['rs-policy']['isBlock'] ?? null);
        self::assertFalse($forNew['rs-product']['loops'] ?? null, 'A snippet not saved yet is contained by none.');
        $this->assertSameShape(
            $this->contract('editor-catalogs.json')['includeCandidates'] ?? null,
            $this->read(self::INCLUDE_CANDIDATES, ['id' => (string) $policy->getId()])
        );
    }

    public function testTheLoadAnswerNamesTheSnippetsThatIncludeABlockAndWhere(): void
    {
        $policy = $this->storeSnippet('rs-policy', 'Return policy', ['@type' => 'MerchantReturnPolicy'], [
            'block' => true,
        ]);
        $product = $this->storeSnippet('rs-product', 'Product', $this->product('{{product.name}}', 'rs-policy'), [
            'kinds' => ['product'],
        ]);

        $answer = $this->read(self::LOAD, ['id' => (string) $policy->getId()]);

        self::assertSame(
            [[
                'id' => $product->getId(),
                'code' => 'rs-product',
                'name' => 'Product',
                'paths' => ['offers.hasMerchantReturnPolicy'],
            ]],
            $answer['usedIn']
        );
        self::assertTrue($this->at($answer, 'snippet', 'isBlock'));
        self::assertContains(
            ['code' => 'product_type', 'label' => 'Product type'],
            $this->listOf($this->at($answer, 'vocabulary', 'conditions', 'product'))
        );
        self::assertSame([], $this->at($answer, 'vocabulary', 'conditions', 'home'));
        $this->assertSameShape($this->contract('editor-load.json')['data'] ?? null, $answer);
        self::assertNull($this->read(self::LOAD)['snippet'], 'A new snippet loads only the vocabulary.');
        self::assertSame(['error' => 'id'], $this->read(self::LOAD, ['id' => '987654'], 400));
    }

    #[
        DataFixture(AttributeFixture::class, ['attribute_code' => 'ean', 'default_frontend_label' => 'EAN'], as: 'ean'),
        DataFixture(
            ProductFixture::class,
            ['name' => 'Editor satchel', 'sku' => 'RS-ED-SATCHEL', 'ean' => '4062001000010'],
            as: 'satchel'
        ),
    ]
    public function testTheFieldCatalogShowsACatalogAttributeWithItsValueOnTheSampleProduct(): void
    {
        $sample = 'product:' . $this->fixtureId('satchel');

        $answer = $this->read(self::FIELDS, ['sample' => $sample]);

        $ean = $this->field($answer, 'product.ean');
        self::assertSame('product_attributes', $ean['group']);
        self::assertSame('EAN', $ean['label']);
        self::assertSame('ean', $ean['attributeCode']);
        self::assertSame('4062001000010', $ean['example']);
        self::assertSame('Editor satchel', $this->field($answer, 'product.name')['example']);
        self::assertSame('Editor satchel', $this->at($answer, 'sample', 'label'));
        self::assertSame(
            ['NewCondition', 'RefurbishedCondition', 'UsedCondition'],
            $this->at($answer, 'enums', 'itemCondition')
        );
        self::assertSame(
            [
                ['name' => 'mpn', 'required' => false, 'field' => null],
                ['name' => 'material', 'required' => false, 'field' => 'product.material'],
                ['name' => 'size', 'required' => false, 'field' => null],
                ['name' => 'pattern', 'required' => false, 'field' => null],
                ['name' => 'category', 'required' => false, 'field' => null],
                ['name' => 'audience', 'required' => false, 'field' => null],
            ],
            $this->at($answer, 'suggestions', 'Product', '')
        );
        self::assertSame(
            ['material' => 'product.material', 'color' => 'product.color'],
            $this->at($answer, 'namedFields', 'Product'),
            'A name typed in takes its field whether or not it is offered.'
        );
        self::assertContains(
            ['path' => 'product.gallery', 'label' => 'Product › Gallery image', 'itemName' => 'image'],
            $this->listOf($answer['lists'])
        );
        self::assertNull($this->field($this->read(self::FIELDS), 'product.ean')['example'], 'No sample, no values.');
        $this->assertSameShape($this->contract('editor-catalogs.json')['fields'] ?? null, $answer);
    }

    #[
        DataFixture(
            ProductFixture::class,
            ['name' => 'Editor sample satchel', 'sku' => 'RS-ED-SAMPLE'],
            as: 'satchel'
        ),
        DataFixture(
            SelectAttributeFixture::class,
            ['attribute_code' => 'rs_ed_colour', 'default_frontend_label' => 'Colour', 'options' => ['Tan', 'Olive']],
            as: 'colour'
        ),
    ]
    public function testTheSamplePagesTheBuilderItsOptionsAndThePlacementCountReadTheCatalog(): void
    {
        $samples = $this->post(self::SAMPLES, ['kinds' => ['product'], 'query' => 'sample satch']);
        $bySku = $this->post(self::SAMPLES, ['kinds' => ['product'], 'query' => 'RS-ED-SAMPLE']);
        $builder = $this->read(self::BUILDER_CONFIG, ['kinds' => 'product']);
        $options = $this->read(
            self::CONDITION_OPTIONS,
            ['field' => 'product_attribute', 'attribute' => 'rs_ed_colour']
        );
        $suggestions = $this->read(self::CONDITION_OPTIONS, ['attribute' => 'name', 'q' => 'sample satch']);
        $all = $this->post(self::PLACEMENT_COUNT, ['pageKinds' => ['product']]);
        $simple = $this->post(self::PLACEMENT_COUNT, [
            'pageKinds' => ['product'],
            'conditions' => $this->conditions('all', [['product_type', '==', 'simple']]),
        ]);
        $others = $this->post(self::PLACEMENT_COUNT, [
            'pageKinds' => ['product'],
            'conditions' => $this->conditions('all', [['product_type', '!=', 'simple']]),
        ]);

        $sample = 'product:' . $this->fixtureId('satchel');
        self::assertSame([$sample], array_column($this->listOf($samples['samples']), 'ref'));
        self::assertSame([$sample], array_column($this->listOf($bySku['samples']), 'ref'));
        self::assertSame('Editor sample satchel', $this->at($samples, 'samples', 0, 'label'));
        $url = $this->at($samples, 'samples', 0, 'url');
        self::assertIsString($url);
        self::assertStringStartsWith($this->storeBaseUrl(), $url);
        self::assertSame('rich_snippets/combine', $this->at($builder, 'config', 'combineType'));
        self::assertSame(
            ['product_attribute_picker', 'attribute_set', 'product_category', 'product_type'],
            array_column($this->listOf($this->at($builder, 'config', 'fields')), 'type')
        );
        self::assertSame(['Tan', 'Olive'], array_column($this->listOf($options['options']), 'label'));
        self::assertContains('Editor sample satchel', array_column($this->listOf($suggestions['items']), 'label'));
        self::assertSame(['product'], array_keys($this->listOrMap($all['kinds'])));
        self::assertSame($all['total'], $all['matching'], 'Without a condition every page of the kind matches.');
        self::assertIsInt($simple['matching']);
        self::assertIsInt($others['matching']);
        self::assertGreaterThanOrEqual(1, $simple['matching']);
        self::assertSame($all['total'], $simple['matching'] + $others['matching']);
        self::assertSame($all['total'], $simple['total']);
        self::assertSame(
            'Output on product pages where Product type is Simple Product, in every store view.',
            $simple['sentence']
        );
        $catalogs = $this->contract('editor-catalogs.json');
        $this->assertSameShape($catalogs['samples'] ?? null, $samples);
        $this->assertSameShape($catalogs['builderConfig'] ?? null, $builder);
        $this->assertSameShape($catalogs['conditionOptions'] ?? null, $options);
        $this->assertSameShape($catalogs['conditionSuggestions'] ?? null, $suggestions);
        $this->assertSameShape($catalogs['placementCount'] ?? null, $simple);
    }

    #[
        DataFixture(
            ProductFixture::class,
            ['name' => 'Editor prefer aaa simple', 'sku' => 'RS-ED-PREFER-SIMPLE'],
            as: 'simple'
        ),
        DataFixture(
            ProductFixture::class,
            ['name' => 'Editor prefer bbb virtual', 'sku' => 'RS-ED-PREFER-VIRTUAL', 'type_id' => 'virtual'],
            as: 'virtual'
        ),
    ]
    public function testTheSamplePagesTheConditionsReachComeFirstThenTheOtherPagesOfTheKind(): void
    {
        $virtual = $this->post(self::SAMPLES, [
            'kinds' => ['product'],
            'query' => 'Editor prefer',
            'conditions' => $this->conditions('all', [['product_type', '==', 'virtual']]),
        ]);
        $none = $this->post(self::SAMPLES, ['kinds' => ['product'], 'query' => 'Editor prefer']);
        $unreadable = $this->post(self::SAMPLES, ['kinds' => ['product'], 'conditions' => '{"type": "nothing"}'], 400);

        $simpleRef = 'product:' . $this->fixtureId('simple');
        $virtualRef = 'product:' . $this->fixtureId('virtual');
        self::assertSame([$virtualRef, $simpleRef], array_column($this->listOf($virtual['samples']), 'ref'));
        self::assertSame([$simpleRef, $virtualRef], array_column($this->listOf($none['samples']), 'ref'));
        self::assertSame(['error' => 'conditions'], $unreadable);
    }

    public function testTheBuilderOffersValueSuggestionsOnlyToAnAdminWhoMayChangeSnippets(): void
    {
        $changer = $this->read(self::BUILDER_CONFIG, ['kinds' => 'product']);
        $this->deny(AdminResources::SNIPPETS);
        $viewer = $this->read(self::BUILDER_CONFIG, ['kinds' => 'product']);

        self::assertIsString($this->at($changer, 'config', 'suggestUrl'));
        self::assertStringContainsString('conditionOptions', (string) $this->at($changer, 'config', 'suggestUrl'));
        self::assertSame('', $this->at($viewer, 'config', 'suggestUrl'));
        self::assertSame($this->at($changer, 'config', 'optionsUrl'), $this->at($viewer, 'config', 'optionsUrl'));
        self::assertSame($this->at($changer, 'config', 'fields'), $this->at($viewer, 'config', 'fields'));
    }

    public function testTheOptionsEndpointAnswersOnlyForWhatAFieldOffers(): void
    {
        self::assertSame(
            ['error' => 'attribute'],
            $this->read(self::CONDITION_OPTIONS, ['field' => 'product_attribute', 'attribute' => 'tier_price'], 400)
        );
        self::assertSame(
            ['error' => 'attribute'],
            $this->read(self::CONDITION_OPTIONS, ['attribute' => 'rs_no_such_attribute'], 400)
        );
        self::assertSame(['error' => 'field'], $this->read(self::CONDITION_OPTIONS, ['field' => 'nothing'], 400));
        self::assertSame(['error' => 'attribute'], $this->read(self::CONDITION_OPTIONS, [], 400));
        self::assertSame(
            ['options' => [['value' => 'virtual', 'label' => 'Virtual Product']]],
            ['options' => array_values(array_filter(
                $this->listOf($this->read(self::CONDITION_OPTIONS, ['field' => 'product_type'])['options']),
                static fn (mixed $option): bool => is_array($option) && ($option['value'] ?? null) === 'virtual'
            ))]
        );
    }

    public function testACountOfConditionsThatDoNotReadIsRefused(): void
    {
        self::assertSame(['error' => 'conditions'], $this->post(self::PLACEMENT_COUNT, [
            'pageKinds' => ['product'],
            'conditions' => $this->conditions('all', [['colour', '==', 'red']]),
        ], 400));
        self::assertSame(['error' => 'kinds'], $this->read(self::BUILDER_CONFIG, ['kinds' => 'blog'], 400));
    }

    /**
     * The builder text of one group of conditions, each `[field, operator, value]`
     *
     * @param string $aggregator
     * @param list<array{0: string, 1: string, 2: string}> $leaves
     * @return string
     */
    private function conditions(string $aggregator, array $leaves): string
    {
        $conditions = [];
        foreach ($leaves as [$field, $operator, $value]) {
            $conditions[] = [
                'type' => $field,
                'attribute' => null,
                'operator' => $operator,
                'value' => $value,
                'is_value_processed' => null,
            ];
        }

        return (string) json_encode([
            'type' => 'rich_snippets/combine',
            'attribute' => null,
            'operator' => null,
            'value' => '1',
            'is_value_processed' => null,
            'aggregator' => $aggregator,
            'conditions' => $conditions,
        ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    /**
     * An answer's object or list
     *
     * @param mixed $value
     * @return array<mixed>
     */
    private function listOrMap(mixed $value): array
    {
        self::assertIsArray($value);

        return $value;
    }

    /**
     * A draft of a stored snippet as the editor sends it, with some values changed
     *
     * @param SnippetInterface $snippet
     * @param array<string, mixed> $changes
     * @return array<string, mixed>
     */
    private function draft(SnippetInterface $snippet, array $changes = []): array
    {
        return array_replace([
            'id' => $snippet->getId(),
            'name' => $snippet->getName(),
            'template' => $snippet->getTemplate(),
            'isBlock' => $snippet->isBlock(),
            'active' => $snippet->isActive(),
            'pageKinds' => $snippet->getPageKinds(),
            'conditions' => '',
            'storeIds' => $snippet->getStoreIds(),
        ], $changes);
    }

    /**
     * A Product template whose name is an expression, including a snippet in its offer when a code is given
     *
     * @param string $name
     * @param string|null $includes
     * @return array<string, mixed>
     */
    private function product(string $name, ?string $includes = null): array
    {
        $offer = ['@type' => 'Offer', 'price' => '{{product.final_price}}'];
        if ($includes !== null) {
            $offer['hasMerchantReturnPolicy'] = ['@include' => $includes];
        }

        return ['@context' => 'https://schema.org', '@type' => 'Product', 'name' => $name, 'offers' => $offer];
    }

    /**
     * Each problem's part, path and code
     *
     * @param mixed $problems
     * @return list<array{part: mixed, path: mixed, code: mixed}>
     */
    private function problemKeys(mixed $problems): array
    {
        self::assertIsArray($problems);
        $keys = [];
        foreach ($problems as $problem) {
            self::assertIsArray($problem);
            $keys[] = ['part' => $problem['part'] ?? null, 'path' => $problem['path'] ?? null,
                'code' => $problem['code'] ?? null];
        }

        return $keys;
    }

    /**
     * A value of an answer that must be a list
     *
     * @param mixed $value
     * @return array<mixed>
     */
    private function listOf(mixed $value): array
    {
        self::assertIsArray($value);

        return $value;
    }

    /**
     * The include candidates by code
     *
     * @param array<mixed> $answer
     * @return array<string, array<mixed>>
     */
    private function candidates(array $answer): array
    {
        $candidates = [];
        foreach ((array) ($answer['candidates'] ?? []) as $candidate) {
            self::assertIsArray($candidate);
            self::assertIsString($candidate['code'] ?? null);
            $candidates[$candidate['code']] = $candidate;
        }

        return $candidates;
    }

    /**
     * A field of the catalog answer, with the code of its group
     *
     * @param array<mixed> $answer
     * @param string $path
     * @return array<mixed>
     */
    private function field(array $answer, string $path): array
    {
        foreach ((array) ($answer['groups'] ?? []) as $group) {
            self::assertIsArray($group);
            foreach ((array) ($group['fields'] ?? []) as $field) {
                if (is_array($field) && ($field['path'] ?? null) === $path) {
                    return $field + ['group' => $group['code'] ?? null];
                }
            }
        }
        self::fail(sprintf('The field catalog has no field "%s".', $path));
    }

    /**
     * Prove an answer has the keys its contract shows, object by object, the first item standing for each list
     *
     * @param mixed $contract
     * @param mixed $answer
     * @param string $path
     * @return void
     */
    private function assertSameShape(mixed $contract, mixed $answer, string $path = '$'): void
    {
        if (!is_array($contract) || $contract === [] || $answer === null) {
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
        if ($this->isMap($path)) {
            return;
        }
        self::assertSame(array_keys($contract), array_keys($answer), $path);
        foreach ($contract as $key => $value) {
            $this->assertSameShape($value, $answer[$key], $path . '.' . $key);
        }
    }

    /**
     * Whether an object is keyed by values (store views, types, properties) rather than by names
     *
     * @param string $path
     * @return bool
     */
    private function isMap(string $path): bool
    {
        return (bool) preg_match(
            '/\.(enums|suggestions|conditions|params|example|output|kinds)$|\.suggestions\.\w+$|\.attributes\[0\]$/',
            $path
        ) || str_ends_with($path, 'vocabulary.conditions');
    }

    /**
     * A contract file, decoded
     *
     * @param string $file
     * @return array<mixed>
     */
    private function contract(string $file): array
    {
        $registrar = Bootstrap::getObjectManager()->get(ComponentRegistrar::class);
        self::assertInstanceOf(ComponentRegistrar::class, $registrar);
        $directory = (string) $registrar->getPath(ComponentRegistrar::MODULE, 'Hryvinskyi_RichSnippets');
        $contract = json_decode((string) file_get_contents($directory . self::CONTRACTS . $file), true);
        self::assertIsArray($contract);

        return $contract;
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
     * The default store view's base address
     *
     * @return non-empty-string
     */
    private function storeBaseUrl(): string
    {
        $storeManager = Bootstrap::getObjectManager()->get(StoreManagerInterface::class);
        self::assertInstanceOf(StoreManagerInterface::class, $storeManager);
        $store = $storeManager->getDefaultStoreView();
        self::assertInstanceOf(Store::class, $store);
        $base = rtrim($store->getBaseUrl(), '/');
        if ($base === '') {
            self::fail('The default store view has a base address.');
        }

        return $base;
    }

    /**
     * @return SnippetRepositoryInterface
     */
    private function repository(): SnippetRepositoryInterface
    {
        $repository = Bootstrap::getObjectManager()->get(SnippetRepositoryInterface::class);
        self::assertInstanceOf(SnippetRepositoryInterface::class, $repository);

        return $repository;
    }
}
