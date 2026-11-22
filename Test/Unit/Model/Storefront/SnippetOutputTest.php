<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Test\Unit\Model\Storefront;

use Hryvinskyi\JsonTemplate\Api\Data\CompiledTemplateInterface;
use Hryvinskyi\JsonTemplate\Api\Exception\CompileException;
use Hryvinskyi\JsonTemplate\Api\IncludeResolverInterface;
use Hryvinskyi\JsonTemplate\Api\ScopeInterface;
use Hryvinskyi\JsonTemplate\Api\TemplateEngineInterface;
use Hryvinskyi\JsonTemplate\Engine\Data\CompiledTemplate;
use Hryvinskyi\JsonTemplate\Engine\Output\JsonEncoder;
use Hryvinskyi\RichSnippets\Api\CollectionLimitsInterface;
use Hryvinskyi\RichSnippets\Api\Data\PageEntityInterface;
use Hryvinskyi\RichSnippets\Api\Data\RenderedBlockInterface;
use Hryvinskyi\RichSnippets\Api\Data\RenderedBlockInterfaceFactory;
use Hryvinskyi\RichSnippets\Api\PageDataPrefetchInterface;
use Hryvinskyi\RichSnippets\Api\PageScopeFactoryInterface;
use Hryvinskyi\RichSnippets\Api\SnippetSelectorInterface;
use Hryvinskyi\RichSnippets\Model\Config\OutputSettings;
use Hryvinskyi\RichSnippets\Model\Storefront\OutputPositionPool;
use Hryvinskyi\RichSnippets\Model\Engine\IncludedTemplates;
use Hryvinskyi\RichSnippets\Model\Engine\TemplatePrefetch;
use Hryvinskyi\RichSnippets\Model\Page\PageEntity;
use Hryvinskyi\RichSnippets\Model\Snippet;
use Hryvinskyi\RichSnippets\Model\Storefront\RenderedBlock;
use Hryvinskyi\RichSnippets\Model\Storefront\SnippetOutput;
use Hryvinskyi\RichSnippets\Test\Unit\Fake\PlacementFixture;
use Magento\Framework\App\Config\ScopeConfigInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;

/**
 * A page writes each of its snippets that has a value, in order; a snippet that fails is logged once and left out;
 * the data every template and include reads is loaded in one batch first; nothing is written while switched off
 */
class SnippetOutputTest extends TestCase
{
    private const PAGE_TEMPLATE = '{"@type": "Product", "name": "{{ product.name }}"}';
    private const EMPTY_TEMPLATE = '{"@type": "FAQPage", "mainEntity": "{{ page.faq }}"}';
    private const BROKEN_TEMPLATE = '{"@type": "Thing", "name": "{{ ( }}"}';
    private const BLOCK_TEMPLATE = '{"@type": "MerchantReturnPolicy", "days": "{{ store.return_days }}"}';

    /**
     * @var array<string, string> settings by path
     */
    private array $settings = [];

    /**
     * @var list<string> logged messages
     */
    private array $logged = [];

    /**
     * @var list<array{array<mixed>, array<mixed>, array<mixed>}> the pages, paths and limits of each batch named
     */
    private array $prefetched = [];

    protected function setUp(): void
    {
        $this->settings = [
            'hryvinskyi_rich_snippets/general/enabled' => '1',
            'hryvinskyi_rich_snippets/output/format' => 'compact',
        ];
        $this->logged = [];
        $this->prefetched = [];
    }

    public function testEachSnippetWithAValueIsWrittenInOrderAndAFailingOneIsLeftOut(): void
    {
        $output = $this->service([
            $this->snippet('product', self::PAGE_TEMPLATE),
            $this->snippet('broken', self::BROKEN_TEMPLATE),
            $this->snippet('faq', self::EMPTY_TEMPLATE),
            $this->snippet('product-copy', self::PAGE_TEMPLATE),
        ]);

        self::assertSame(
            [
                ['product', 'Thing', '{"@type":"Product","name":"Field \u003Cb\u003ESatchel\u003C/b\u003E"}'],
                ['product-copy', 'Thing', '{"@type":"Product","name":"Field \u003Cb\u003ESatchel\u003C/b\u003E"}'],
            ],
            $this->blocks($output->forPage($this->page()))
        );
        self::assertCount(1, $this->logged);
        self::assertStringContainsString('"broken"', $this->logged[0]);
    }

    public function testAFailingSnippetIsLoggedOncePerRequest(): void
    {
        $output = $this->service([$this->snippet('broken', self::BROKEN_TEMPLATE)]);

        $output->forPage($this->page());
        $output->forPage($this->page());
        self::assertCount(1, $this->logged);

        $output->_resetState();
        $output->forPage($this->page());
        self::assertCount(2, $this->logged);
    }

    public function testTheDataOfEveryTemplateAndIncludeIsNamedInOneBatch(): void
    {
        $this->service([
            $this->snippet('product', self::PAGE_TEMPLATE),
            $this->snippet('faq', self::EMPTY_TEMPLATE),
        ])->forPage($this->page());

        self::assertCount(1, $this->prefetched);
        [$pages, $paths, $limits] = $this->prefetched[0];
        self::assertCount(1, $pages);
        self::assertInstanceOf(PageEntityInterface::class, $pages[0]);
        self::assertSame($this->page()->getEntityId(), $pages[0]->getEntityId());
        self::assertSame(['product.name', 'page.faq', 'store.return_days'], $paths);
        self::assertSame(['templates' => 3], $limits);
    }

    public function testReadableFormatIndentsTheJson(): void
    {
        $this->settings['hryvinskyi_rich_snippets/output/format'] = 'readable';
        $blocks = $this->service([$this->snippet('product', self::PAGE_TEMPLATE)])->forPage($this->page());

        self::assertSame(
            "{\n    \"@type\": \"Product\",\n    \"name\": \"Field \\u003Cb\\u003ESatchel\\u003C/b\\u003E\"\n}",
            $blocks[0]->getJson()
        );
    }

    public function testNothingIsWrittenOrSelectedWhileTheSnippetsAreSwitchedOffForTheStoreView(): void
    {
        $this->settings['hryvinskyi_rich_snippets/general/enabled'] = '0';
        $selector = $this->createMock(SnippetSelectorInterface::class);
        $selector->expects(self::never())->method('select');

        self::assertSame([], $this->service([], $selector)->forPage($this->page()));
    }

    /**
     * The output service over fakes: an engine that knows the test's templates and an include of the page template
     *
     * @param list<Snippet> $snippets
     * @param SnippetSelectorInterface|null $selector
     * @return SnippetOutput
     */
    private function service(array $snippets, ?SnippetSelectorInterface $selector = null): SnippetOutput
    {
        $config = $this->createMock(ScopeConfigInterface::class);
        $config->method('getValue')->willReturnCallback(fn (string $path): ?string => $this->settings[$path] ?? null);
        $config->method('isSetFlag')->willReturnCallback(
            fn (string $path): bool => ($this->settings[$path] ?? '0') === '1'
        );

        if ($selector === null) {
            $selector = $this->createMock(SnippetSelectorInterface::class);
            $selector->method('select')->willReturn($snippets);
        }

        $compiled = [
            self::PAGE_TEMPLATE => new CompiledTemplate([], ['product'], ['product.name'], ['policy'], 'page'),
            self::EMPTY_TEMPLATE => new CompiledTemplate([], ['page'], ['page.faq'], [], 'empty'),
            self::BLOCK_TEMPLATE => new CompiledTemplate([], ['store'], ['store.return_days'], [], 'block'),
        ];
        $values = [
            self::PAGE_TEMPLATE => ['@type' => 'Product', 'name' => 'Field <b>Satchel</b>'],
            self::EMPTY_TEMPLATE => null,
        ];
        $engine = $this->createMock(TemplateEngineInterface::class);
        $engine->method('compile')->willReturnCallback(
            static fn (string $source): CompiledTemplateInterface => $compiled[$source]
                ?? throw new CompileException([])
        );
        $engine->method('evaluate')->willReturnCallback(
            static fn (string $source): mixed => $values[$source] ?? null
        );

        $includes = $this->createMock(IncludeResolverInterface::class);
        $includes->method('resolve')->willReturnCallback(
            static fn (string $code): ?CompiledTemplateInterface => $code === 'policy'
                ? $compiled[self::BLOCK_TEMPLATE]
                : null
        );

        $limits = $this->createMock(CollectionLimitsInterface::class);
        $limits->method('limits')->willReturnCallback(static fn (array $templates): array => [
            'templates' => count($templates),
        ]);

        $prefetch = $this->createMock(PageDataPrefetchInterface::class);
        $prefetch->method('prefetch')->willReturnCallback(
            function (array $pages, array $paths, array $collectionLimits): void {
                $this->prefetched[] = [$pages, $paths, $collectionLimits];
            }
        );

        $scopes = $this->createMock(PageScopeFactoryInterface::class);
        $scopes->method('create')->willReturn($this->createMock(ScopeInterface::class));

        $blockFactory = $this->createMock(RenderedBlockInterfaceFactory::class);
        $blockFactory->method('create')->willReturnCallback(
            static function (array $data): RenderedBlockInterface {
                self::assertIsString($data['code']);
                self::assertIsString($data['type']);
                self::assertIsString($data['json']);

                return new RenderedBlock($data['code'], $data['type'], $data['json']);
            }
        );

        return new SnippetOutput(
            new OutputSettings($config, new OutputPositionPool()),
            $selector,
            $engine,
            new TemplatePrefetch(new IncludedTemplates($includes), $limits, $prefetch),
            $scopes,
            new JsonEncoder(),
            $blockFactory,
            $this->logger()
        );
    }

    /**
     * A logger that adds each message to the test's list
     *
     * @return AbstractLogger
     */
    private function logger(): AbstractLogger
    {
        return new class (function (string $message): void {
            $this->logged[] = $message;
        }) extends AbstractLogger {
            /**
             * @param \Closure(string): void $keep
             */
            public function __construct(private readonly \Closure $keep)
            {
            }

            /**
             * @inheritDoc
             */
            public function log($level, string|\Stringable $message, array $context = []): void
            {
                ($this->keep)((string) $message);
            }
        };
    }

    /**
     * A switched-on product page snippet
     *
     * @param string $code
     * @param string $template
     * @return Snippet
     */
    private function snippet(string $code, string $template): Snippet
    {
        return new Snippet(
            1,
            $code,
            ucfirst($code),
            $template,
            'Thing',
            false,
            true,
            ['product'],
            PlacementFixture::none(),
            [0],
            null,
            0,
            null,
            ''
        );
    }

    /**
     * The product page of the test
     *
     * @return PageEntity
     */
    private function page(): PageEntity
    {
        return new PageEntity('product', 7, 1);
    }

    /**
     * The code, type and JSON of each block
     *
     * @param list<RenderedBlockInterface> $blocks
     * @return list<array{string, string, string}>
     */
    private function blocks(array $blocks): array
    {
        return array_map(
            static fn (RenderedBlockInterface $block): array => [
                $block->getCode(),
                $block->getType(),
                $block->getJson(),
            ],
            $blocks
        );
    }
}
