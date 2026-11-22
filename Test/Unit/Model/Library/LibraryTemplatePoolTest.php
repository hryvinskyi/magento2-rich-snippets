<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Test\Unit\Model\Library;

use Hryvinskyi\RichSnippets\Api\Data\GoogleStatus;
use Hryvinskyi\RichSnippets\Api\Data\LibraryTemplateInterface;
use Hryvinskyi\RichSnippets\Api\Exception\MalformedLibraryTemplateException;
use Hryvinskyi\RichSnippets\Api\LibraryTemplateSourceInterface;
use Hryvinskyi\RichSnippets\Model\Config\ModuleJsonFile;
use Hryvinskyi\RichSnippets\Model\Library\Source\ModuleLibraryFile;
use Hryvinskyi\RichSnippets\Test\Unit\Fake\InMemoryCache;
use Hryvinskyi\RichSnippets\Test\Unit\Fake\LibraryDefinitionSource;
use Hryvinskyi\RichSnippets\Test\Unit\Fake\LibraryFixture;
use Magento\Framework\Filesystem\Driver\File;
use Magento\Framework\Module\Dir;
use Magento\Framework\Serialize\Serializer\Json;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The module's library files load as templates, and a definition that is not exactly a template's shape fails the
 * load with its code and the key at fault
 */
class LibraryTemplatePoolTest extends TestCase
{
    private const MODULE_FILES = [
        'organization', 'website-name', 'breadcrumbs', 'local-business', 'product', 'product-variants',
        'product-list', 'category', 'faq', 'article', 'return-policy', 'shipping-policy', 'postal-address',
    ];

    public function testTheModulesThirteenTemplatesLoadInTheirGroupsOrder(): void
    {
        $pool = LibraryFixture::pool($this->moduleSources(), new InMemoryCache());

        $codes = array_map(static fn (LibraryTemplateInterface $t): string => $t->getCode(), $pool->all());

        self::assertSame(self::MODULE_FILES, $codes);
        self::assertSame(
            ['shop' => 4, 'product' => 2, 'category' => 2, 'content' => 2, 'blocks' => 3],
            array_count_values(array_map(
                static fn (LibraryTemplateInterface $template): string => $template->getGroup(),
                $pool->all()
            ))
        );
    }

    public function testTheProductTemplateIsReadWithItsPlacementQuestionsAndGoogleStatusFromTheRules(): void
    {
        $product = LibraryFixture::pool($this->moduleSources(), new InMemoryCache())->get('product');

        self::assertNotNull($product);
        self::assertSame('Product', $product->getType());
        self::assertSame(1, $product->getVersion());
        self::assertFalse($product->isBlock());
        self::assertSame(['product'], $product->getPageKinds());
        $leaves = $product->getConditions()->getLeaves();
        self::assertCount(1, $leaves);
        self::assertSame('product_type', $leaves[0]->getField());
        self::assertSame('!=', $leaves[0]->getOperator());
        self::assertSame('configurable', $leaves[0]->getValue());
        self::assertSame(['return-policy'], $product->getUses());
        self::assertSame(['merchant_listing', 'review_stars'], $product->getResultTypes());
        self::assertSame(GoogleStatus::Ok, $product->getGoogleStatus());
        self::assertSame(
            ['brand', 'gtin', 'description'],
            array_map(static fn ($q): string => $q->getCode(), $product->getQuestions())
        );
        self::assertSame('product', $product->getQuestions()[0]->getRoot());
        self::assertSame('Price, stock, brand, rating of a product', $product->getSummary());
    }

    public function testATemplateWithoutASummaryHasAnEmptyOne(): void
    {
        $faq = LibraryFixture::pool($this->moduleSources(), new InMemoryCache())->get('faq');

        self::assertNotNull($faq);
        self::assertSame('', $faq->getSummary());
    }

    public function testTheStatusOfATemplateNoRuleCoversIsTheOneItStatesAndAGoneRuleMakesItGone(): void
    {
        $pool = LibraryFixture::pool($this->moduleSources(), new InMemoryCache());

        self::assertSame(GoogleStatus::Gone, $pool->get('faq')?->getGoogleStatus());
        self::assertSame(GoogleStatus::None, $pool->get('product-list')?->getGoogleStatus());
        self::assertSame(GoogleStatus::Ok, $pool->get('article')?->getGoogleStatus());
        self::assertSame('variant', $pool->get('product-variants')?->getQuestions()[1]->getRoot());
    }

    public function testBuildingBlocksHaveNoPlacement(): void
    {
        $pool = LibraryFixture::pool($this->moduleSources(), new InMemoryCache());

        foreach (['return-policy', 'shipping-policy', 'postal-address'] as $code) {
            self::assertTrue($pool->get($code)?->isBlock());
            self::assertSame([], $pool->get($code)->getPageKinds());
        }
    }

    public function testTheNextProcessReadsTheDefinitionsFromTheCache(): void
    {
        $cache = new InMemoryCache();
        $first = new LibraryDefinitionSource($this->definition());
        LibraryFixture::pool(['website-name' => $first], $cache)->all();
        $second = new LibraryDefinitionSource($this->definition());

        $templates = LibraryFixture::pool(['website-name' => $second], $cache)->all();

        self::assertSame(1, $first->reads);
        self::assertSame(0, $second->reads);
        self::assertSame('Website name', $templates[0]->getName());
    }

    public function testATemplateThatDoesNotCompileWithItsPlaceholdersLeftOutFailsTheLoad(): void
    {
        $pool = LibraryFixture::pool(
            ['website-name' => new LibraryDefinitionSource($this->definition())],
            new InMemoryCache(),
            static fn (string $source): bool => false
        );

        $this->expectException(MalformedLibraryTemplateException::class);
        $this->expectExceptionMessage('"website-name" does not compile');

        $pool->all();
    }

    public function testAPlaceholderIsTakenOutBeforeTheTemplateIsCompiled(): void
    {
        $compiled = [];
        $definition = $this->definition();
        $definition['questions'] = [['code' => 'brand', 'label' => 'Brand', 'suggester' => 'brand']];
        $definition['template']['alternateName'] = '{{? brand }}';
        $pool = LibraryFixture::pool(
            ['website-name' => new LibraryDefinitionSource($definition)],
            new InMemoryCache(),
            static function (string $source) use (&$compiled): bool {
                $compiled[] = $source;

                return true;
            }
        );

        $pool->all();

        self::assertCount(1, $compiled);
        self::assertStringNotContainsString('{{?', $compiled[0]);
        self::assertStringNotContainsString('alternateName', $compiled[0]);
    }

    public function testATemplateMayUseOnlyABuildingBlockOfTheLibrary(): void
    {
        $definition = $this->definition();
        $definition['uses'] = ['website-name'];
        $definition['template']['publisher'] = ['@include' => 'website-name'];
        $pool = LibraryFixture::pool(['website-name' => new LibraryDefinitionSource($definition)], new InMemoryCache());

        $this->expectException(MalformedLibraryTemplateException::class);
        $this->expectExceptionMessage('uses "website-name"');

        $pool->all();
    }

    /**
     * @param array<string,mixed> $change
     * @param string $message
     */
    #[DataProvider('malformedDefinitions')]
    public function testAMalformedDefinitionIsRefusedAtItsKey(array $change, string $message): void
    {
        $definition = array_replace_recursive($this->definition(), $change);
        if (array_key_exists('unset', $change)) {
            unset($definition['unset']);
            foreach ((array) $change['unset'] as $key) {
                unset($definition[is_string($key) ? $key : '']);
            }
        }

        $this->expectException(MalformedLibraryTemplateException::class);
        $this->expectExceptionMessage($message);

        LibraryFixture::parser()->parse('website-name', $definition, array_keys(LibraryFixture::GROUPS));
    }

    /**
     * @return array<string, array{array<string, mixed>, string}>
     */
    public static function malformedDefinitions(): array
    {
        return [
            'an unknown key' => [['colour' => 'red'], 'unknown key "colour"'],
            'a summary that is no text' => [['summary' => 3], '"summary"'],
            'no version' => [['version' => 0], '"version"'],
            'an unknown group' => [['group' => 'misc'], '"group"'],
            'an unknown rule' => [['google' => ['results' => ['sitelinks']]], '"google.results[0]"'],
            'a status beside rules' => [['google' => ['status' => 'ok']], '"google.status"'],
            'an unknown kind of page' => [['placement' => ['pageKinds' => ['blog']]], '"placement.pageKinds[0]"'],
            'a field of another kind' => [
                ['placement' => ['conditions' => self::tree('product_type', '==')]],
                '"placement.conditions[0]"',
            ],
            'an operator the field does not offer' => [
                ['placement' => ['pageKinds' => ['product'], 'conditions' => self::tree('product_type', '{}')]],
                'conditions that read (condition_operator_unknown)',
            ],
            'an unknown field' => [
                ['placement' => ['pageKinds' => ['product'], 'conditions' => self::tree('colour', '==')]],
                'conditions that read (condition_field_unknown)',
            ],
            'a list of conditions' => [
                ['placement' => ['pageKinds' => ['product'], 'conditions' => [
                    ['field' => 'product_type', 'op' => 'is', 'value' => 'simple'],
                ]]],
                'the expression builder\'s tree',
            ],
            'a page template without placement' => [['unset' => ['placement']], '"placement"'],
            'a block with placement' => [['block' => true, 'template' => ['@context' => null]], '"placement"'],
            'an unknown suggester' => [
                ['questions' => [['code' => 'brand', 'label' => 'Brand', 'suggester' => 'colour']]],
                '"questions[0].suggester"',
            ],
            'a question without its placeholder' => [
                ['questions' => [['code' => 'brand', 'label' => 'Brand', 'suggester' => 'brand']]],
                'one placeholder per question',
            ],
            'a placeholder without its question' => [
                ['template' => ['alternateName' => '{{? brand }}']],
                'one placeholder per question',
            ],
            'a placeholder written loosely' => [
                ['template' => ['alternateName' => 'Shop {{? brand }}']],
                'placeholders written exactly',
            ],
            'an include not listed in uses' => [
                ['template' => ['publisher' => ['@include' => 'postal-address']]],
                '"uses"',
            ],
            'a block in uses not included' => [['uses' => ['postal-address']], '"uses"'],
            'a root without a type' => [['template' => ['@type' => 'website']], '"template.@type"'],
            'a template that is no object' => [['template' => 'x'], '"template"'],
        ];
    }

    public function testATemplateNamingNoRuleMustStateItsStatus(): void
    {
        $definition = $this->definition();
        $definition['google']['results'] = [];

        $this->expectException(MalformedLibraryTemplateException::class);
        $this->expectExceptionMessage('"google.status"');

        LibraryFixture::parser()->parse('website-name', $definition, array_keys(LibraryFixture::GROUPS));
    }

    public function testABuildingBlockWithAContextIsRefused(): void
    {
        $definition = $this->definition();
        $definition['block'] = true;
        unset($definition['placement']);

        $this->expectException(MalformedLibraryTemplateException::class);
        $this->expectExceptionMessage('"template.@context"');

        LibraryFixture::parser()->parse('website-name', $definition, array_keys(LibraryFixture::GROUPS));
    }

    public function testACodeOutsideTheCodeGrammarIsRefused(): void
    {
        $this->expectException(MalformedLibraryTemplateException::class);
        $this->expectExceptionMessage('"Website_Name" is not a library template code');

        LibraryFixture::parser()->parse('Website_Name', $this->definition(), array_keys(LibraryFixture::GROUPS));
    }

    /**
     * A small valid definition: the module's Website name
     *
     * @return array{version: int, group: string, name: string, icon: string, description: string, where: string,
     *     google: array<string, mixed>, properties: list<string>, placement: array<string, mixed>,
     *     template: array<string, mixed>, block?: bool, uses?: list<string>,
     *     questions?: list<array<string, string>>}
     */
    private function definition(): array
    {
        return [
            'version' => 1,
            'group' => 'shop',
            'name' => 'Website name',
            'icon' => 'language',
            'description' => 'The name Google shows for the site.',
            'where' => 'Home page',
            'google' => ['results' => ['site_name'], 'short' => 'Google: site name', 'long' => 'It needs a name.'],
            'properties' => ['name', 'alternateName', 'url'],
            'placement' => ['pageKinds' => ['home']],
            'template' => [
                '@context' => 'https://schema.org',
                '@type' => 'WebSite',
                'name' => '{{store.name}}',
                'url' => '{{store.url}}',
            ],
        ];
    }

    /**
     * The sources of the module's library files
     *
     * @return array<string, LibraryTemplateSourceInterface>
     */
    private function moduleSources(): array
    {
        $dirs = $this->createStub(Dir::class);
        $dirs->method('getDir')->willReturn(dirname(__DIR__, 4) . '/etc');
        $files = new ModuleJsonFile($dirs, new File(), new Json());
        $sources = [];
        foreach (self::MODULE_FILES as $code) {
            $sources[$code] = new ModuleLibraryFile($files, 'Hryvinskyi_RichSnippets', $code . '.json');
        }

        return $sources;
    }

    /**
     * A decoded builder tree of one condition with the value `simple`
     *
     * @param string $field
     * @param string $operator
     * @return array<string, mixed>
     */
    private static function tree(string $field, string $operator): array
    {
        return [
            'type' => 'rich_snippets/combine',
            'attribute' => null,
            'operator' => null,
            'value' => '1',
            'is_value_processed' => null,
            'aggregator' => 'all',
            'conditions' => [[
                'type' => $field,
                'attribute' => null,
                'operator' => $operator,
                'value' => 'simple',
                'is_value_processed' => null,
            ]],
        ];
    }
}
