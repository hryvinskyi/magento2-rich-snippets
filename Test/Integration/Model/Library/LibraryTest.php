<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Test\Integration\Model\Library;

use Hryvinskyi\JsonTemplate\Api\TemplateEngineInterface;
use Hryvinskyi\RichSnippets\Api\Data\AddedSnippetInterface;
use Hryvinskyi\RichSnippets\Api\Data\AnswerOptionInterface;
use Hryvinskyi\RichSnippets\Api\Data\HintTone;
use Hryvinskyi\RichSnippets\Api\Data\LibraryEntryInterface;
use Hryvinskyi\RichSnippets\Api\Data\QuestionSuggestionsInterface;
use Hryvinskyi\RichSnippets\Api\Exception\IdempotencyException;
use Hryvinskyi\RichSnippets\Api\Exception\IdempotencyReason;
use Hryvinskyi\RichSnippets\Api\Exception\LibraryException;
use Hryvinskyi\RichSnippets\Api\Exception\LibraryReason;
use Hryvinskyi\RichSnippets\Api\FrontendEmulationInterface;
use Hryvinskyi\RichSnippets\Api\LibraryInterface;
use Hryvinskyi\RichSnippets\Api\LibraryTemplatePoolInterface;
use Hryvinskyi\RichSnippets\Api\PageEntityFactoryInterface;
use Hryvinskyi\RichSnippets\Api\PageScopeFactoryInterface;
use Hryvinskyi\RichSnippets\Api\SnippetRepositoryInterface;
use Hryvinskyi\RichSnippets\Model\Cache\Type\RichSnippetsCache;
use Hryvinskyi\RichSnippets\Model\Catalog\ProductAttributes;
use Hryvinskyi\RichSnippets\Model\Library\AnswerSubstitution;
use Hryvinskyi\RichSnippets\Model\Library\Question\BrandSuggester;
use Hryvinskyi\RichSnippets\Model\Library\Question\ShopNameInterface;
use Magento\Catalog\Test\Fixture\Attribute as AttributeFixture;
use Magento\Catalog\Test\Fixture\Product as ProductFixture;
use Magento\Cms\Test\Fixture\Block as BlockFixture;
use Magento\Framework\ObjectManager\ResetAfterRequestInterface;
use Magento\Framework\ObjectManagerInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Fixture\DataFixtureStorageManager;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * The library through the real container: list flags, adds with their building blocks and names, idempotent replays,
 * suggestions counted over the shop's product pages, and answers written so the template language reads them
 * literally
 */
#[DbIsolation(true)]
class LibraryTest extends TestCase
{
    private const RECOMMENDED = ['organization', 'website-name', 'breadcrumbs', 'product', 'product-variants'];

    /**
     * @var ObjectManagerInterface
     */
    private ObjectManagerInterface $objectManager;

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
    }

    public function testEveryTemplateCompilesWithEveryAnswerItsQuestionsOffer(): void
    {
        $substitution = $this->get(AnswerSubstitution::class);
        $compiled = 0;
        foreach ($this->get(LibraryTemplatePoolInterface::class)->all() as $template) {
            $suggestions = $this->library()->suggestions($template->getCode());
            $defaults = [];
            foreach ($suggestions as $question) {
                $defaults[$question->getQuestion()] = $this->option($question, $question->getDefault());
            }
            $variants = [$defaults];
            foreach ($suggestions as $question) {
                foreach ($question->getOptions() as $option) {
                    $variants[] = [$question->getQuestion() => $option] + $defaults;
                }
            }
            foreach ($variants as $answers) {
                $this->engine()->compile(json_encode(
                    $substitution->substitute($template->getTemplate(), $answers, []),
                    JSON_THROW_ON_ERROR
                ));
                $compiled++;
            }
        }

        self::assertGreaterThan(13, $compiled);
    }

    public function testTheListOffersThirteenTemplatesAndFlagsTheOnesAddedAfterAnAdd(): void
    {
        $before = $this->entry('product');
        self::assertCount(13, $this->library()->list());
        self::assertFalse($before->isAdded());

        $this->library()->add('product', [], $this->key());
        $after = $this->entry('product');

        self::assertTrue($after->isAdded());
        self::assertSame([1], $after->getAddedVersions());
        self::assertFalse($after->hasNewerVersion());
        self::assertSame(['return-policy'], $after->getBlocksInShop());
        self::assertSame($before->getNextName() . ' 2', $after->getNextName());
        self::assertTrue($this->entry('return-policy')->isAdded(), 'The building block it uses was added too.');
        self::assertFalse($this->entry('organization')->isAdded());
        self::assertSame(['return-policy'], $this->entry('organization')->getBlocksInShop());
    }

    public function testAddingProductTwiceNamesTheSecondCopyProduct2AndReusesTheReturnPolicy(): void
    {
        $first = $this->library()->add('product', [], $this->key());
        $second = $this->library()->add('product', [], $this->key());

        self::assertSame(['return-policy', 'product'], $this->templatesOf($first));
        self::assertFalse($first[0]->isReused());
        self::assertTrue($first[0]->isBlock());
        self::assertSame(['return-policy', 'product'], $this->templatesOf($second));
        self::assertTrue($second[0]->isReused(), 'The second add reuses the block the first one added.');
        self::assertSame($first[0]->getId(), $second[0]->getId());
        self::assertSame('Product', $first[1]->getName());
        self::assertSame('Product 2', $second[1]->getName());

        $copy = $this->repository()->get($second[1]->getId());
        self::assertFalse($copy->isActive());
        self::assertSame('product', $copy->getOrigin()?->getCode());
        self::assertSame(1, $copy->getOrigin()->getVersion());
        self::assertSame(['product'], $copy->getPageKinds());
        self::assertSame([0], $copy->getStoreIds());
        self::assertStringContainsString('"@include": "' . $first[0]->getCode() . '"', $copy->getTemplate());
        self::assertStringNotContainsString('{{?', $copy->getTemplate());
    }

    public function testTheRecommendedSetAddsFiveSnippetsAndTheTwoBlocksTheyShareAllSwitchedOff(): void
    {
        $count = count($this->repository()->list());
        $key = $this->key();

        $added = $this->library()->addRecommended($key);

        self::assertSame(
            ['return-policy', 'postal-address', 'organization', 'website-name', 'breadcrumbs', 'product',
                'product-variants'],
            $this->templatesOf($added)
        );
        self::assertCount($count + 7, $this->repository()->list());
        foreach ($added as $snippet) {
            self::assertFalse($snippet->isReused());
            self::assertFalse($this->repository()->get($snippet->getId())->isActive());
        }
        self::assertSame([true, true, false, false, false, false, false], array_map(
            static fn (AddedSnippetInterface $snippet): bool => $snippet->isBlock(),
            $added
        ));
        foreach (self::RECOMMENDED as $code) {
            self::assertTrue($this->entry($code)->isAdded(), $code . ' is flagged as added.');
        }
    }

    public function testTheRecommendedSetRepeatedWithTheSameKeyCreatesNothingAndAnswersTheSame(): void
    {
        $key = $this->key();
        $first = $this->library()->addRecommended($key);
        $count = count($this->repository()->list());

        $again = $this->library()->addRecommended($key);

        self::assertCount($count, $this->repository()->list());
        self::assertSame($this->idsOf($first), $this->idsOf($again));
        self::assertSame(
            array_map(static fn (AddedSnippetInterface $s): string => $s->getName(), $first),
            array_map(static fn (AddedSnippetInterface $s): string => $s->getName(), $again)
        );
    }

    public function testTheRecommendedSetWithANewKeyAddsOnlyTheRecommendedTemplatesTheShopLacks(): void
    {
        $this->library()->add('website-name', [], $this->key());

        $added = $this->library()->addRecommended($this->key());

        self::assertNotContains('website-name', $this->templatesOf($added));
        self::assertContains('organization', $this->templatesOf($added));
    }

    public function testAnAddRepeatedWithTheSameKeyCreatesOneSnippet(): void
    {
        $key = $this->key();
        $first = $this->library()->add('website-name', [], $key);
        $count = count($this->repository()->list());

        $again = $this->library()->add('website-name', [], $key);

        self::assertSame($this->idsOf($first), $this->idsOf($again));
        self::assertCount($count, $this->repository()->list());
    }

    public function testAKeyUsedForAnotherKindOfWriteIsRefused(): void
    {
        $key = $this->key();
        $this->library()->add('website-name', [], $key);

        try {
            $this->library()->addRecommended($key);
            self::fail('A key of an add was accepted for the recommended set.');
        } catch (IdempotencyException $e) {
            self::assertSame(IdempotencyReason::KeyReused, $e->getReason());
        }
    }

    public function testAnInvalidKeyIsRefusedBeforeAnythingIsWritten(): void
    {
        $count = count($this->repository()->list());

        try {
            $this->library()->add('website-name', [], 'not a key');
            self::fail('A key with spaces was accepted.');
        } catch (IdempotencyException $e) {
            self::assertSame(IdempotencyReason::KeyInvalid, $e->getReason());
        }
        self::assertCount($count, $this->repository()->list());
    }

    public function testAnUnknownTemplateQuestionOrAnswerIsRefusedAndNothingIsCreated(): void
    {
        $count = count($this->repository()->list());
        $refusals = [];
        foreach ([
            ['recipe', []],
            ['product', ['colour' => 'red']],
            ['product', ['brand' => 'attribute:colour']],
        ] as [$code, $answers]) {
            try {
                $this->library()->add($code, $answers, $this->key());
            } catch (LibraryException $e) {
                $refusals[] = $e->getReason();
            }
        }

        self::assertSame(
            [LibraryReason::TemplateUnknown, LibraryReason::QuestionUnknown, LibraryReason::AnswerUnknown],
            $refusals
        );
        self::assertCount($count, $this->repository()->list());
    }

    public function testAnAnswerThatLeavesThePropertyOutRemovesItFromTheCopy(): void
    {
        $added = $this->library()->add('product', ['brand' => 'leave_out', 'gtin' => 'leave_out'], $this->key());

        $template = $this->repository()->get($added[1]->getId())->getTemplate();

        self::assertStringNotContainsString('"brand"', $template);
        self::assertStringNotContainsString('"gtin13"', $template);
        self::assertStringContainsString('"description"', $template);
    }

    #[
        DataFixture(AttributeFixture::class, ['attribute_code' => 'ean', 'default_frontend_label' => 'EAN'], as: 'ean'),
        DataFixture(ProductFixture::class, ['sku' => 'lib-ean-1', 'ean' => '4062001000010'], as: 'first'),
        DataFixture(ProductFixture::class, ['sku' => 'lib-ean-2', 'ean' => '4062001000027'], as: 'second'),
        DataFixture(ProductFixture::class, ['sku' => 'lib-ean-3'], as: 'third'),
    ]
    public function testTheGtinQuestionFindsTheAttributeByItsCodeAndCountsTheProductPagesThatFillIt(): void
    {
        $gtin = $this->suggestion('product', 'gtin');
        $ean = $this->option($gtin, 'attribute:ean');

        self::assertSame(2, $ean->getFillCount());
        self::assertSame('attribute:ean', $gtin->getDefault(), 'The most filled candidate is suggested.');
        self::assertSame(HintTone::Ok, $ean->getTone());
        self::assertSame('{{product.ean}}', $ean->getFragment());
        self::assertSame('EAN · attribute ean', $ean->getLabel());
        self::assertStringStartsWith('Found by its code. Filled for 2 of ', $ean->getHint());
        self::assertGreaterThanOrEqual(3, $gtin->getTotal());
        self::assertTrue($this->option($gtin, 'leave_out')->isLeftOut());
        self::assertSame(
            '{{variant.ean}}',
            $this->option($this->suggestion('product-variants', 'gtin'), 'attribute:ean')->getFragment(),
            'A question placed inside the variants reads the variant.'
        );
    }

    public function testTheBrandAndDescriptionQuestionsOfferTheirConfiguredAttributes(): void
    {
        $brand = $this->suggestion('product', 'brand');
        $description = $this->suggestion('product', 'description');

        self::assertSame(
            'Manufacturer · attribute manufacturer',
            $this->option($brand, 'attribute:manufacturer')->getLabel()
        );
        self::assertTrue($this->option($brand, 'leave_out')->isLeftOut());
        self::assertSame(
            ['attribute:short_description', 'attribute:description', 'attribute:meta_description'],
            array_map(
                static fn (AnswerOptionInterface $option): string => $option->getKey(),
                $description->getOptions()
            )
        );
        self::assertSame(
            '{{product.short_description | plain | limit:5000}}',
            $this->option($description, 'attribute:short_description')->getFragment()
        );
    }

    #[
        DataFixture(BlockFixture::class, [
            'identifier' => 'lib-follow-us',
            'content' => '<a href="https://www.instagram.com/linden">I</a> <a href="https://x.com/linden">X</a>'
                . ' <a href="https://www.youtube.com/@linden">Y</a> <a href="https://www.pinterest.com/linden">P</a>'
                . ' <a href="https://www.linkedin.com/company/linden">L</a> <a href="https://example.com">no</a>',
        ]),
        DataFixture(BlockFixture::class, [
            'identifier' => 'lib-one-link',
            'content' => '<a href="https://www.facebook.com/linden">F</a>',
        ]),
        DataFixture(BlockFixture::class, [
            'identifier' => 'lib-switched-off',
            'active' => false,
            'content' => '<a href="https://x.com/off">X</a>'
                . '<a href="https://x.com/a">a</a><a href="https://x.com/b">b</a><a href="https://x.com/c">c</a>'
                . '<a href="https://x.com/d">d</a><a href="https://x.com/e">e</a><a href="https://x.com/f">f</a>',
        ]),
    ]
    public function testTheSocialProfilesComeFromTheActiveCmsBlockWithTheMostProfileLinks(): void
    {
        $profiles = $this->suggestion('organization', 'social_profiles');
        $block = $this->option($profiles, 'cms_block:lib-follow-us');

        self::assertSame('type_in', $profiles->getDefault());
        self::assertSame([], $this->option($profiles, 'type_in')->getFragment());
        self::assertSame('Take the 5 links from the CMS block “lib-follow-us”', $block->getLabel());
        self::assertSame('5 links found in the CMS block “lib-follow-us”.', $block->getHint());
        self::assertCount(2, $profiles->getOptions(), 'Only the block with the most links is offered.');

        $added = $this->library()->add('organization', ['social_profiles' => 'cms_block:lib-follow-us'], $this->key());
        $template = json_decode(
            $this->repository()->get($added[2]->getId())->getTemplate(),
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        self::assertSame('organization', $added[2]->getTemplate());
        self::assertIsArray($template);
        self::assertSame(
            [
                'https://www.instagram.com/linden',
                'https://x.com/linden',
                'https://www.youtube.com/@linden',
                'https://www.pinterest.com/linden',
                'https://www.linkedin.com/company/linden',
            ],
            $template['sameAs']
        );
    }

    #[DataFixture(ProductFixture::class, ['sku' => 'lib-brand'], as: 'product')]
    public function testAShopNameWithQuotesBracesAndPipesReachesTheOutputLiterally(): void
    {
        $shop = 'O\'Hara "&" }} | Co {{x}}';
        $fake = new class ($shop) implements ShopNameInterface {
            public function __construct(private readonly string $shop)
            {
            }

            public function name(int $storeId): string
            {
                return $this->shop;
            }
        };
        $brand = $this->objectManager->create(
            BrandSuggester::class,
            ['shopName' => $fake, 'attributes' => ['manufacturer' => 'manufacturer']]
        );
        self::assertInstanceOf(BrandSuggester::class, $brand);
        $template = $this->get(LibraryTemplatePoolInterface::class)->get('product');
        self::assertNotNull($template);
        $question = $template->getQuestions()[0];
        $suggestions = $brand->suggest($question, $this->storeId());

        foreach (['attribute:manufacturer', 'shop_name'] as $key) {
            $source = json_encode(
                $this->get(AnswerSubstitution::class)->withoutAnswers([
                    '@type' => 'Product',
                    'brand' => ['@type' => 'Brand', 'name' => $this->option($suggestions, $key)->getFragment()],
                ]),
                JSON_THROW_ON_ERROR
            );
            $output = $this->evaluate($source, $this->productId());

            self::assertIsArray($output);
            self::assertSame(['@type' => 'Brand', 'name' => $shop], $output['brand'] ?? null, $key);
        }
    }

    /**
     * The library
     *
     * @return LibraryInterface
     */
    private function library(): LibraryInterface
    {
        return $this->get(LibraryInterface::class);
    }

    /**
     * The snippet repository
     *
     * @return SnippetRepositoryInterface
     */
    private function repository(): SnippetRepositoryInterface
    {
        return $this->get(SnippetRepositoryInterface::class);
    }

    /**
     * The library entry of a template
     *
     * @param string $code
     * @return LibraryEntryInterface
     */
    private function entry(string $code): LibraryEntryInterface
    {
        foreach ($this->library()->list() as $entry) {
            if ($entry->getTemplate()->getCode() === $code) {
                return $entry;
            }
        }
        self::fail('The library has no template ' . $code);
    }

    /**
     * The suggestions of one question of a template
     *
     * @param string $template
     * @param string $question
     * @return QuestionSuggestionsInterface
     */
    private function suggestion(string $template, string $question): QuestionSuggestionsInterface
    {
        foreach ($this->library()->suggestions($template) as $suggestions) {
            if ($suggestions->getQuestion() === $question) {
                return $suggestions;
            }
        }
        self::fail(sprintf('The template %s asks no question %s.', $template, $question));
    }

    /**
     * The answer with a key
     *
     * @param QuestionSuggestionsInterface $suggestions
     * @param string $key
     * @return AnswerOptionInterface
     */
    private function option(QuestionSuggestionsInterface $suggestions, string $key): AnswerOptionInterface
    {
        foreach ($suggestions->getOptions() as $option) {
            if ($option->getKey() === $key) {
                return $option;
            }
        }
        self::fail(sprintf('The question %s offers no answer %s.', $suggestions->getQuestion(), $key));
    }

    /**
     * A template evaluated for a product page of the default store view, as its storefront
     *
     * @param string $source
     * @param int $productId
     * @return mixed
     */
    private function evaluate(string $source, int $productId): mixed
    {
        $page = $this->get(PageEntityFactoryInterface::class)->create('product', $productId, $this->storeId());

        return $this->get(FrontendEmulationInterface::class)->run(
            $this->storeId(),
            fn (): mixed => $this->engine()->evaluate(
                $source,
                $this->get(PageScopeFactoryInterface::class)->create($page)
            )
        );
    }

    /**
     * The snippets' own engine
     *
     * @return TemplateEngineInterface
     */
    private function engine(): TemplateEngineInterface
    {
        $engine = $this->objectManager->get('HryvinskyiRichSnippetsTemplateEngine');
        self::assertInstanceOf(TemplateEngineInterface::class, $engine);

        return $engine;
    }

    /**
     * The fixture product's ID
     *
     * @return int
     */
    private function productId(): int
    {
        $id = DataFixtureStorageManager::getStorage()->get('product')?->getData('entity_id');
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
     * The library codes of added snippets, in order
     *
     * @param list<AddedSnippetInterface> $added
     * @return list<string>
     */
    private function templatesOf(array $added): array
    {
        return array_map(static fn (AddedSnippetInterface $snippet): string => $snippet->getTemplate(), $added);
    }

    /**
     * The IDs of added snippets, in order
     *
     * @param list<AddedSnippetInterface> $added
     * @return list<int>
     */
    private function idsOf(array $added): array
    {
        return array_map(static fn (AddedSnippetInterface $snippet): int => $snippet->getId(), $added);
    }

    /**
     * A fresh idempotency key
     *
     * @return string
     */
    private function key(): string
    {
        return 'test-' . bin2hex(random_bytes(12));
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
