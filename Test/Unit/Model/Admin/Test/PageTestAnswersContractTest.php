<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Test\Unit\Model\Admin\Test;

use Hryvinskyi\RichSnippets\Api\Data\ConditionTreeInterface;
use Hryvinskyi\RichSnippets\Api\Data\ExclusionReason;
use Hryvinskyi\RichSnippets\Api\Data\ReadFailure;
use Hryvinskyi\RichSnippets\Api\Data\TestedBlockInterface;
use Hryvinskyi\RichSnippets\Api\Data\UnwrittenReason;
use Hryvinskyi\RichSnippets\Api\PageSearchInterface;
use Hryvinskyi\RichSnippets\Api\PageTestInterface;
use Hryvinskyi\RichSnippets\Model\Admin\Editor\EditorPresenter;
use Hryvinskyi\RichSnippets\Model\Admin\Editor\EditorRequest;
use Hryvinskyi\RichSnippets\Model\Admin\Editor\ProblemPhrases;
use Hryvinskyi\RichSnippets\Model\Admin\Test\BlockRows;
use Hryvinskyi\RichSnippets\Model\Admin\Test\Feature\LeftOutWording;
use Hryvinskyi\RichSnippets\Model\Admin\Test\Feature\MetWording;
use Hryvinskyi\RichSnippets\Model\Admin\Test\Feature\NotMetWording;
use Hryvinskyi\RichSnippets\Model\Admin\Test\FeatureRows;
use Hryvinskyi\RichSnippets\Model\Admin\Test\NotOutputReasons;
use Hryvinskyi\RichSnippets\Model\Admin\Test\PageTestPresenter;
use Hryvinskyi\RichSnippets\Model\Admin\Test\ReadFailurePhrases;
use Hryvinskyi\RichSnippets\Model\Admin\Test\Summary\BlockSummaries;
use Hryvinskyi\RichSnippets\Model\Admin\Test\Summary\BreadcrumbSummary;
use Hryvinskyi\RichSnippets\Model\Admin\Test\Summary\OrganizationSummary;
use Hryvinskyi\RichSnippets\Model\Admin\Test\Summary\ProductSummary;
use Hryvinskyi\RichSnippets\Model\Admin\Test\Summary\WebSiteSummary;
use Hryvinskyi\RichSnippets\Model\Admin\Test\TestAnswers;
use Hryvinskyi\RichSnippets\Model\Condition\Tree\ConditionLeaf;
use Hryvinskyi\RichSnippets\Model\Wording\WordList;
use Hryvinskyi\RichSnippets\Model\Html\JsonLdBlock;
use Hryvinskyi\RichSnippets\Model\Html\MicrodataItem;
use Hryvinskyi\RichSnippets\Model\Page\PageEntity;
use Hryvinskyi\RichSnippets\Model\Page\PageMatch;
use Hryvinskyi\RichSnippets\Model\PageTest\PageTestResult;
use Hryvinskyi\RichSnippets\Model\PageTest\TestedBlock;
use Hryvinskyi\RichSnippets\Model\PageTest\UnwrittenSnippet;
use Hryvinskyi\RichSnippets\Model\Placement\ConditionMismatch;
use Hryvinskyi\RichSnippets\Model\Selection\SnippetExclusion;
use Hryvinskyi\RichSnippets\Model\Snippet;
use Hryvinskyi\RichSnippets\Model\Storefront\Reading\AddressParser;
use Hryvinskyi\RichSnippets\Model\Storefront\Reading\StorefrontPath;
use Hryvinskyi\RichSnippets\Test\Unit\Fake\EnglishPlurals;
use Hryvinskyi\RichSnippets\Test\Unit\Fake\ModuleChecker;
use Hryvinskyi\RichSnippets\Test\Unit\Fake\PlacementFixture;
use Hryvinskyi\RichSnippets\Test\Unit\Model\Admin\Test\Reason\ExclusionWordingsTest;
use Hryvinskyi\RichSnippets\Test\Unit\Model\Requirement\DesignPreviews;
use PHPUnit\Framework\TestCase;

/**
 * The Test page's answers are written exactly as the contract files its browser code is tested against: the page
 * picker's search, and a page test that meets everything, one with a problem, one that outputs nothing and one that
 * could not be read
 *
 * @phpstan-import-type Answer from PageTestPresenter
 */
class PageTestAnswersContractTest extends TestCase
{
    private const CONTRACTS = __DIR__ . '/../../../../_contract/admin-pages/';
    private const TESTED_AT = 1795269120;

    public function testThePagePickersSearchIsWrittenAsTheContractWritesIt(): void
    {
        self::assertSame($this->contract('test-page-search.json'), self::travelled($this->search()));
    }

    public function testEachStateOfAPageTestIsWrittenAsTheContractWritesIt(): void
    {
        self::assertSame($this->contract('test-page-run.json'), self::travelled(self::runs()));
    }

    public function testTwoBlocksOfOneTypeListEachKindOfResultOnceTheWorstVerdictWinning(): void
    {
        $features = self::runs()['twice']['features'];

        self::assertSame(
            [
                ['merchant_listing', 'not_met', ['image']],
                ['review_stars', 'met', []],
            ],
            array_map(
                static fn (array $feature): array => [
                    $feature['rule'],
                    $feature['verdict'],
                    $feature['missingRequired'],
                ],
                $features
            )
        );
        self::assertSame(
            ['gtin', 'offers.shippingDetails', 'aggregateRating', 'review'],
            $features[0]['missingRecommended']
        );
    }

    public function testAVerdictWithoutAWordingFailsLoudly(): void
    {
        $block = self::block('product', self::snippet(3, 'product', 'Product', ['product']), DesignPreviews::satchel());

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('No wording is configured for the verdict "met".');

        (new FeatureRows())->rows([$block]);
    }

    public function testABlockWithAProblemCountsAsBadAndSaysWhatIsMissing(): void
    {
        $problem = self::runs()['problem'];

        self::assertSame(
            [
                ['bad', 'Merchant listing: image missing.'],
                ['bad', 'The JSON of this block cannot be read, so search engines ignore it.'],
            ],
            array_map(static fn (array $block): array => [$block['verdict'], $block['summary']], $problem['blocks'])
        );
    }

    /**
     * The search answers for a text that is a path, and for one that is not
     *
     * @return array<string, mixed>
     */
    private function search(): array
    {
        $satchel = new PageMatch(
            new PageEntity('product', 12, 1),
            'Field satchel',
            'https://linden.example/bags/field-satchel.html'
        );
        $home = new PageMatch(new PageEntity('home', 2, 1), 'Home page', 'https://linden.example/');
        $request = $this->createMock(EditorRequest::class);
        $request->method('query')->willReturnCallback(
            static fn (array $query): string => is_string($query['query'] ?? null) ? $query['query'] : ''
        );
        $request->method('storeId')->willReturn(1);
        $pageSearch = $this->createMock(PageSearchInterface::class);
        $pageSearch->method('search')->willReturnCallback(
            static fn (string $query): array => $query === '/checkout/cart' ? [] : [$home, $satchel]
        );
        $answers = new TestAnswers(
            $request,
            new EditorPresenter(new ProblemPhrases(), PlacementFixture::printer()),
            $pageSearch,
            new StorefrontPath(new AddressParser()),
            $this->createMock(PageTestInterface::class),
            self::presenter()
        );

        return [
            'pages' => $answers->search(['query' => 'a', 'store' => '1']),
            'address' => $answers->search(['query' => '/checkout/cart', 'store' => '1']),
        ];
    }

    /**
     * The page tests of the design's satchel, care kit, cart, a storefront in maintenance and a page two snippets of
     * one type output on
     *
     * @return array<string, Answer>
     */
    public static function runs(): array
    {
        $presenter = self::presenter();
        $satchel = new PageEntity('product', 12, 1);
        $product = self::snippet(3, 'product', 'Product', ['product']);
        $crumbs = self::snippet(5, 'breadcrumbs', 'Breadcrumbs', ['product', 'category', 'cms']);
        $organization = self::snippet(1, 'organization', 'Organization', ['home']);
        $list = self::snippet(6, 'product-list', 'Product list', ['category'], false);
        $variants = new ConditionMismatch(
            new ConditionLeaf('product_type', null, '==', 'configurable'),
            'product type Configurable',
            'Product type',
            'is',
            ['Configurable'],
            'Simple'
        );

        return [
            'done' => $presenter->present(new PageTestResult(
                $satchel,
                'Field satchel',
                '/bags/field-satchel.html',
                '/bags/field-satchel.html',
                self::TESTED_AT,
                412,
                null,
                null,
                [
                    self::block('product', $product, DesignPreviews::satchel()),
                    self::block('breadcrumbs', $crumbs, DesignPreviews::breadcrumbs()),
                ],
                [new MicrodataItem('Product', ['name', 'offers', 'aggregateRating'])],
                [
                    new SnippetExclusion($organization, ExclusionReason::OtherKinds),
                    new SnippetExclusion(
                        self::snippet(
                            4,
                            'product-variants',
                            'Product with variants',
                            ['product'],
                            true,
                            PlacementFixture::leaves([['product_type', '==', 'configurable']])
                        ),
                        ExclusionReason::ConditionsNotMet,
                        [$variants]
                    ),
                    new SnippetExclusion($list, ExclusionReason::SwitchedOff),
                    new SnippetExclusion(self::snippet(8, 'faq', 'FAQ', ['cms']), ExclusionReason::OtherKinds),
                ],
                [new UnwrittenSnippet(self::snippet(9, 'shipping', 'Shipping', ['product']), UnwrittenReason::Empty)]
            )),
            'problem' => $presenter->present(new PageTestResult(
                new PageEntity('product', 13, 1),
                'Leather care kit',
                '/accessories/leather-care-kit.html',
                '/accessories/leather-care-kit.html',
                self::TESTED_AT,
                380,
                null,
                null,
                [
                    self::block('product', $product, DesignPreviews::kit()),
                    self::broken(),
                ]
            )),
            'nothing' => $presenter->present(new PageTestResult(
                new PageEntity('other', null, 1),
                '/checkout/cart',
                '/checkout/cart',
                '/checkout/cart',
                self::TESTED_AT,
                296,
                null,
                null,
                [],
                [],
                [
                    new SnippetExclusion($organization, ExclusionReason::OtherKinds),
                    new SnippetExclusion($crumbs, ExclusionReason::OtherKinds),
                    new SnippetExclusion(
                        self::snippet(7, 'trade', 'Trade prices', ['product']),
                        ExclusionReason::OtherStores
                    ),
                ]
            )),
            'twice' => $presenter->present(new PageTestResult(
                $satchel,
                'Field satchel',
                '/bags/field-satchel.html',
                '/bags/field-satchel.html',
                self::TESTED_AT,
                401,
                null,
                null,
                [
                    self::block('product', $product, DesignPreviews::satchel()),
                    self::block(
                        'product-copy',
                        self::snippet(10, 'product-copy', 'Product copy', ['product']),
                        DesignPreviews::kit()
                    ),
                ]
            )),
            'failed' => $presenter->present(new PageTestResult(
                $satchel,
                'Field satchel',
                '/bags/field-satchel.html',
                null,
                self::TESTED_AT,
                64,
                ReadFailure::HttpStatus,
                503
            )),
        ];
    }

    /**
     * The presenter as the container builds it
     *
     * @return PageTestPresenter
     */
    private static function presenter(): PageTestPresenter
    {
        $words = new WordList();
        $shop = new OrganizationSummary(new EnglishPlurals(), 'The shop');

        return new PageTestPresenter(
            new EditorPresenter(new ProblemPhrases(), PlacementFixture::printer()),
            new BlockRows(new BlockSummaries([
                'Product' => new ProductSummary(new EnglishPlurals()),
                'BreadcrumbList' => new BreadcrumbSummary(),
                'WebSite' => new WebSiteSummary(),
                'OnlineStore' => $shop,
            ]), $words),
            new FeatureRows([
                'met' => new MetWording(),
                'not_met' => new NotMetWording(),
                'left_out' => new LeftOutWording(),
            ]),
            new NotOutputReasons(ExclusionWordingsTest::wordings()),
            new ReadFailurePhrases(),
            $words
        );
    }

    /**
     * A block the page carries in compact form, checked as the page test checks it
     *
     * @param string $code
     * @param Snippet $snippet
     * @param array<string, mixed> $value
     * @return TestedBlockInterface
     */
    private static function block(string $code, Snippet $snippet, array $value): TestedBlockInterface
    {
        $type = is_string($value['@type'] ?? null) ? $value['@type'] : '';

        return new TestedBlock(
            new JsonLdBlock($code, (string) json_encode($value, JSON_UNESCAPED_SLASHES), $value),
            $snippet,
            $type,
            [$value],
            [ModuleChecker::create()->checkOutput($type, $value)]
        );
    }

    /**
     * A block no snippet wrote, whose JSON cannot be read
     *
     * @return TestedBlockInterface
     */
    private static function broken(): TestedBlockInterface
    {
        return new TestedBlock(new JsonLdBlock(null, '{"@type": "Product",', null), null, '', [], []);
    }

    /**
     * A page snippet
     *
     * @param int $id
     * @param string $code
     * @param string $name
     * @param list<string> $kinds
     * @param bool $active
     * @param ConditionTreeInterface|null $conditions none when null
     * @return Snippet
     */
    private static function snippet(
        int $id,
        string $code,
        string $name,
        array $kinds,
        bool $active = true,
        ?ConditionTreeInterface $conditions = null
    ): Snippet {
        return new Snippet(
            $id,
            $code,
            $name,
            '{"@type": "Thing"}',
            'Thing',
            false,
            $active,
            $kinds,
            $conditions ?? PlacementFixture::none(),
            [0],
            null,
            0,
            '2026-11-12 09:30:00',
            'admin'
        );
    }

    /**
     * Answers as they travel: written as JSON and read back
     *
     * @param array<mixed> $answers
     * @return array<mixed>
     */
    private static function travelled(array $answers): array
    {
        $decoded = json_decode(
            (string) json_encode($answers, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION),
            true,
            512,
            JSON_THROW_ON_ERROR
        );
        self::assertIsArray($decoded);

        return $decoded;
    }

    /**
     * A contract file, decoded
     *
     * @param string $file
     * @return array<mixed>
     */
    private function contract(string $file): array
    {
        $contract = json_decode((string) file_get_contents(self::CONTRACTS . $file), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($contract);

        return $contract;
    }
}
