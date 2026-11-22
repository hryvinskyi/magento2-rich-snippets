<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Test\Unit\Model\Admin\Overview;

use Hryvinskyi\RichSnippets\Api\Data\AttentionItemInterface;
use Hryvinskyi\RichSnippets\Api\Data\AttentionTone;
use Hryvinskyi\RichSnippets\Api\Data\CheckRunInterface;
use Hryvinskyi\RichSnippets\Api\Data\CheckState;
use Hryvinskyi\RichSnippets\Api\Data\CheckTrigger;
use Hryvinskyi\RichSnippets\Api\Data\ConditionTreeInterface;
use Hryvinskyi\RichSnippets\Api\Data\FeatureOverviewInterface;
use Hryvinskyi\RichSnippets\Api\Data\KindOverviewInterface;
use Hryvinskyi\RichSnippets\Api\Data\OutputFormat;
use Hryvinskyi\RichSnippets\Api\Data\SetupState;
use Hryvinskyi\RichSnippets\Api\Data\ThemeMarkupState;
use Hryvinskyi\RichSnippets\Model\Admin\Overview\AttentionRows;
use Hryvinskyi\RichSnippets\Model\Admin\Overview\GoogleFeatureRows;
use Hryvinskyi\RichSnippets\Model\Admin\Overview\KindRows;
use Hryvinskyi\RichSnippets\Model\Admin\Overview\OverviewPresenter;
use Hryvinskyi\RichSnippets\Model\Admin\Overview\ScreenLinks;
use Hryvinskyi\RichSnippets\Model\Admin\Overview\State\LiveState;
use Hryvinskyi\RichSnippets\Model\Admin\Overview\State\NoneState;
use Hryvinskyi\RichSnippets\Model\Admin\Overview\State\OffState;
use Hryvinskyi\RichSnippets\Model\Admin\Overview\State\StateWordings;
use Hryvinskyi\RichSnippets\Model\Admin\Overview\StatusCard;
use Hryvinskyi\RichSnippets\Model\Admin\Overview\StoreNames;
use Hryvinskyi\RichSnippets\Model\Check\CheckRun;
use Hryvinskyi\RichSnippets\Model\Overview\AttentionItem;
use Hryvinskyi\RichSnippets\Model\Overview\AttentionTarget;
use Hryvinskyi\RichSnippets\Model\Overview\FeatureOverview;
use Hryvinskyi\RichSnippets\Model\Overview\KindOverview;
use Hryvinskyi\RichSnippets\Model\Overview\KindSnippet;
use Hryvinskyi\RichSnippets\Model\Overview\OverviewReport;
use Hryvinskyi\RichSnippets\Model\Placement\KindLabels;
use Hryvinskyi\RichSnippets\Model\Placement\LimitingValue;
use Hryvinskyi\RichSnippets\Model\Placement\KindPhrases;
use Hryvinskyi\RichSnippets\Model\Placement\PlacementCount;
use Hryvinskyi\RichSnippets\Model\Requirement\Check\FeatureTally;
use Hryvinskyi\RichSnippets\Model\Snippet;
use Hryvinskyi\RichSnippets\Model\Wording\CountWords;
use Hryvinskyi\RichSnippets\Model\Wording\WordList;
use Hryvinskyi\RichSnippets\Test\Unit\Fake\BuildsCheckWording;
use Hryvinskyi\RichSnippets\Test\Unit\Fake\DictionaryRenderer;
use Hryvinskyi\RichSnippets\Test\Unit\Fake\EnglishPlurals;
use Hryvinskyi\RichSnippets\Test\Unit\Fake\FixedClock;
use Hryvinskyi\RichSnippets\Test\Unit\Fake\ModuleRules;
use Hryvinskyi\RichSnippets\Test\Unit\Fake\GrantedPermissions;
use Hryvinskyi\RichSnippets\Test\Unit\Fake\ModulePositions;
use Hryvinskyi\RichSnippets\Test\Unit\Fake\PlacementFixture;
use Magento\Framework\Phrase;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\TestCase;

/**
 * The Overview's answer is written exactly as the contract file its browser code is tested against: live with
 * nothing to fix, live with four things to fix, one store view picked, nothing set up yet, and everything off
 */
class OverviewAnswersContractTest extends TestCase
{
    use BuildsCheckWording;

    private const CONTRACTS = __DIR__ . '/../../../../_contract/admin-pages/';
    private const NOW = 1795269500;
    private const STARTED = 1795230000;
    private const FIRST_ADDED = 1794560400;
    private const ENGLISH = 1;
    private const DEUTSCH = 2;
    private const TRADE = 3;
    private const STORES = [self::ENGLISH => 'English', self::DEUTSCH => 'Deutsch', self::TRADE => 'Trade'];

    public function testEveryStateOfTheOverviewIsWrittenAsTheContractWritesIt(): void
    {
        $presenter = $this->presenter();

        self::assertSame($this->contract('overview-data.json'), self::travelled([
            'healthy' => $presenter->present($this->live(null, [], [], ThemeMarkupState::Removed)),
            'attention' => $presenter->present(
                $this->live(null, ['product' => 2], $this->attention(), ThemeMarkupState::StillThere)
            ),
            'deutsch' => $presenter->present($this->live(self::DEUTSCH, [], [], ThemeMarkupState::NoneSeen)),
            'none' => $presenter->present($this->none()),
            'off' => $presenter->present($this->off()),
        ]));
    }

    public function testThePartsOfAChipAreJoinedByTheSeparatorTheLanguageWrites(): void
    {
        $renderer = Phrase::getRenderer();
        Phrase::setRenderer(new DictionaryRenderer(['%1, %2' => '%1 / %2']));
        try {
            $answer = $this->presenter()->present($this->live(null, [], [], ThemeMarkupState::Removed));
        } finally {
            Phrase::setRenderer($renderer);
        }

        $subs = [];
        foreach ($answer['kinds']['rows'] as $row) {
            array_push($subs, ...array_column($row['snippets'], 'sub'));
        }
        self::assertContains('2 pages / English only', $subs);
    }

    public function testTheStatusSaysPagesWhenNotEveryInvalidPageIsAProduct(): void
    {
        $answer = $this->presenter()->present(
            $this->live(null, ['product' => 1, 'category' => 2], [], ThemeMarkupState::Removed)
        );

        self::assertSame(['invalid', '3 pages are invalid'], [$answer['status']['tone'], $answer['status']['word']]);
    }

    public function testAScreenWithoutParametersIsLinkedWithAnEmptyObject(): void
    {
        $answer = $this->presenter()->present($this->off());

        self::assertSame(
            '{"label":"Open Snippets","route":"hryvinskyi_richsnippets/snippets/index","params":{}}',
            json_encode($answer['status']['action'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)
        );
    }

    public function testWithoutTheRightToRunTestsNothingOffersToTestAPage(): void
    {
        $answer = $this->presenter(new GrantedPermissions(['snippets' => true, 'test' => false]))
            ->present($this->live(null, [], [], ThemeMarkupState::Removed));

        self::assertNull($answer['status']['action']);
        self::assertSame([], array_filter(array_column($answer['kinds']['rows'], 'see')));
        self::assertNotSame([], $answer['kinds']['rows']);
    }

    public function testTheThemeMarkupStillThereIsAWarningFact(): void
    {
        $answer = $this->presenter()->present($this->off());

        self::assertSame(
            ['theme', 'Still there', 'warn'],
            [$answer['facts'][3]['key'], $answer['facts'][3]['value'], $answer['facts'][3]['tone']]
        );
    }

    /**
     * The Overview while live, over every store view or in one
     *
     * @param int|null $storeId
     * @param array<string,int> $invalid the last check's invalid pages by kind
     * @param list<AttentionItemInterface> $items
     * @param ThemeMarkupState $themeMarkup
     * @return OverviewReport
     */
    private function live(?int $storeId, array $invalid, array $items, ThemeMarkupState $themeMarkup): OverviewReport
    {
        $snippets = $this->snippets($storeId === null);
        $on = array_values(array_filter($snippets, static fn (Snippet $snippet): bool => $snippet->isActive()));

        return new OverviewReport(
            $storeId,
            SetupState::Live,
            count($snippets),
            count($on),
            ['home', 'product', 'category', 'cms'],
            $storeId === null ? [self::ENGLISH, self::DEUTSCH, self::TRADE] : [$storeId],
            self::FIRST_ADDED,
            $this->lastRun(),
            $storeId === null ? 208 : 72,
            $invalid,
            OutputFormat::Compact,
            'head',
            $themeMarkup,
            $this->kinds($snippets),
            $this->features($storeId === null, $invalid),
            $items
        );
    }

    /**
     * The Overview before anything was added
     *
     * @return OverviewReport
     */
    private function none(): OverviewReport
    {
        return new OverviewReport(
            null,
            SetupState::None,
            0,
            0,
            [],
            [],
            null,
            null,
            null,
            [],
            OutputFormat::Compact,
            'head',
            ThemeMarkupState::StillThere,
            $this->kinds([]),
            [],
            []
        );
    }

    /**
     * The Overview with the five recommended snippets added and switched off, the theme's markup flagged
     *
     * @return OverviewReport
     */
    private function off(): OverviewReport
    {
        $snippets = [
            self::snippet(1, 'organization', 'Organization', ['home'], false),
            self::snippet(2, 'website', 'Website name', ['home'], false),
            self::snippet(3, 'product', 'Product', ['product'], false, self::notConfigurable()),
            self::snippet(4, 'product-variants', 'Product with variants', ['product'], false, self::configurable()),
            self::snippet(5, 'breadcrumbs', 'Breadcrumbs', ['product', 'category', 'cms'], false),
        ];

        return new OverviewReport(
            null,
            SetupState::Off,
            5,
            0,
            [],
            [],
            self::FIRST_ADDED,
            null,
            null,
            [],
            OutputFormat::Compact,
            'head',
            ThemeMarkupState::StillThere,
            $this->kinds($snippets),
            [],
            [$this->themeItem()]
        );
    }

    /**
     * The design's eight page snippets; over every store view the FAQ for English only is among them
     *
     * @param bool $everyStoreView
     * @return list<Snippet>
     */
    private function snippets(bool $everyStoreView): array
    {
        $snippets = [
            self::snippet(1, 'organization', 'Organization', ['home']),
            self::snippet(2, 'website', 'Website name', ['home']),
            self::snippet(3, 'product', 'Product', ['product'], true, self::notConfigurable()),
            self::snippet(4, 'product-variants', 'Product with variants', ['product'], true, self::configurable()),
            self::snippet(5, 'breadcrumbs', 'Breadcrumbs', ['product', 'category', 'cms']),
            self::snippet(6, 'product-list', 'Product list', ['category'], false),
            self::snippet(
                7,
                'shop-in-berlin',
                'Shop in Berlin',
                ['cms'],
                true,
                PlacementFixture::leaves([['cms_page', '==', 'contact']])
            ),
        ];
        if ($everyStoreView) {
            $snippets[] = self::snippet(
                8,
                'faq',
                'FAQ',
                ['cms'],
                true,
                PlacementFixture::leaves([['cms_page', '()', 'faq,shipping']]),
                [self::ENGLISH]
            );
        }

        return $snippets;
    }

    /**
     * The page map of some snippets
     *
     * @param list<Snippet> $snippets
     * @return list<KindOverviewInterface>
     */
    private function kinds(array $snippets): array
    {
        $pages = ['home' => 1, 'product' => 184, 'category' => 21, 'cms' => 9, 'other' => null];
        $samples = ['home' => 2, 'product' => 12, 'category' => 3, 'cms' => 4, 'other' => null];
        $matching = ['product' => [3 => 146, 4 => 38], 'cms' => [7 => 1, 8 => 2]];
        $kinds = [];
        foreach ($pages as $kind => $count) {
            $set = [];
            foreach ($snippets as $snippet) {
                if (!in_array($kind, $snippet->getPageKinds(), true)) {
                    continue;
                }
                $id = (int) $snippet->getId();
                $set[] = new KindSnippet(
                    $snippet,
                    isset($matching[$kind][$id]) ? new PlacementCount($matching[$kind][$id], (int) $count) : null
                );
            }
            $kinds[] = new KindOverview($kind, $count, $count === null ? null : $samples[$kind], $set);
        }
        return $kinds;
    }

    /**
     * The last check's results; FAQ only where it is set
     *
     * @param bool $everyStoreView
     * @param array<string,int> $invalid
     * @return list<FeatureOverviewInterface>
     */
    private function features(bool $everyStoreView, array $invalid): array
    {
        $rules = ModuleRules::pool();
        $notMet = $invalid['product'] ?? 0;
        $tallies = [
            ['merchant_listing', 184 - $notMet, $notMet, 0, ['product']],
            ['review_stars', 113, 0, 71, ['product']],
            ['product_variants', 38, 0, 0, ['product']],
            ['breadcrumb', 207, 0, 0, ['product', 'category', 'cms']],
            ['organization', 1, 0, 0, ['home']],
            ['site_name', 1, 0, 0, ['home']],
        ];
        if ($everyStoreView) {
            $tallies[] = ['faq', 2, 0, 0, ['cms']];
        }
        $features = [];
        foreach ($tallies as [$code, $met, $missing, $leftOut, $kinds]) {
            $rule = $rules->get($code);
            self::assertNotNull($rule, $code);
            $features[] = new FeatureOverview(new FeatureTally($rule, $met, $missing, $leftOut), $kinds);
        }

        return $features;
    }

    /**
     * The four things to fix of the design's attention state
     *
     * @return list<AttentionItemInterface>
     */
    private function attention(): array
    {
        return [
            new AttentionItem(
                'finding-merchant-listing-image',
                AttentionTone::Bad,
                '%1 on %2',
                ['No image', '2 products'],
                '%1',
                ['Google needs image for a merchant listing. These pages are output without it and are reported as'
                    . ' invalid.'],
                'See them',
                new AttentionTarget(
                    'hryvinskyi_richsnippets/test/index',
                    ['mode' => 'catalog', 'finding' => 'merchant-listing-image']
                )
            ),
            $this->themeItem(),
            new AttentionItem(
                'finding-merchant-listing-gtin13-product-ean',
                AttentionTone::Warn,
                '%1 on %2',
                ['EAN is empty', '23 products'],
                '%1',
                ['gtin13 is left out. Google recommends it for a merchant listing.'],
                'See them',
                new AttentionTarget(
                    'hryvinskyi_richsnippets/test/index',
                    ['mode' => 'catalog', 'finding' => 'merchant-listing-gtin13-product-ean']
                )
            ),
            new AttentionItem(
                'template-product',
                AttentionTone::Info,
                'The %1 template has a newer version',
                ['Product'],
                'Your snippet is unchanged. The library can add the new version beside it, switched off.',
                [],
                'Open the library',
                new AttentionTarget('hryvinskyi_richsnippets/library/index', ['template' => 'product'])
            ),
        ];
    }

    /**
     * The theme's markup still on the product pages
     *
     * @return AttentionItemInterface
     */
    private function themeItem(): AttentionItemInterface
    {
        return new AttentionItem(
            'theme-markup',
            AttentionTone::Warn,
            'The theme still writes its own Product markup',
            [],
            'Product pages carry two descriptions of the same product, which can disagree.',
            [],
            'Open Settings',
            new AttentionTarget('adminhtml/system_config/edit', ['section' => 'hryvinskyi_rich_snippets'])
        );
    }

    /**
     * Last night's run: 03:00, 41 seconds, three store views
     *
     * @return CheckRunInterface
     */
    private function lastRun(): CheckRunInterface
    {
        return new CheckRun(
            7,
            CheckState::Done,
            CheckTrigger::Schedule,
            self::STARTED - 10,
            self::STARTED,
            self::STARTED + 41,
            self::STARTED + 41,
            208,
            208,
            3
        );
    }

    /**
     * The presenter as the container builds it, at the test's time, the store views named as in the design
     *
     * @param GrantedPermissions $permissions what the admin may do; everything when left out
     * @return OverviewPresenter
     */
    private function presenter(GrantedPermissions $permissions = new GrantedPermissions()): OverviewPresenter
    {
        $clock = new FixedClock(self::NOW);
        $times = $this->runTimes($clock);
        $storeManager = $this->createMock(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturnCallback(function (mixed $id): StoreInterface {
            $store = $this->createMock(StoreInterface::class);
            $store->method('getName')->willReturn(self::STORES[is_int($id) ? $id : 0] ?? '');

            return $store;
        });
        $stores = new StoreNames($storeManager);
        $timezone = $this->createMock(TimezoneInterface::class);
        $timezone->method('date')->willReturnCallback(
            static fn (mixed $seconds): \DateTime => (new \DateTime('@' . (is_int($seconds) ? $seconds : 0)))
                ->setTimezone(new \DateTimeZone('UTC'))
        );
        $timezone->method('formatDate')->willReturnCallback(
            static fn (mixed $date): string => $date instanceof \DateTimeInterface ? $date->format('j F Y') : ''
        );
        $words = new WordList();
        $links = new ScreenLinks();

        return new OverviewPresenter(
            new StateWordings([
                SetupState::None->value => new NoneState(),
                SetupState::Off->value => new OffState($timezone, $stores, $links, new EnglishPlurals()),
                SetupState::Live->value => new LiveState(
                    $stores,
                    $links,
                    $permissions,
                    new EnglishPlurals(),
                    new CountWords(new EnglishPlurals())
                ),
            ]),
            new StatusCard($times, ModulePositions::pool()),
            new KindRows($words, $stores, $links, new KindLabels([
                'home' => ['label' => 'Home page', 'icon' => 'home'],
                'product' => ['label' => 'Product pages', 'icon' => 'inventory_2'],
                'category' => ['label' => 'Category pages', 'icon' => 'category'],
                'cms' => ['label' => 'CMS pages', 'icon' => 'article'],
                'other' => [
                    'label' => 'Every other page',
                    'icon' => 'public',
                    'description' => 'Cart, checkout, account, search',
                ],
            ]), $permissions, new LimitingValue(PlacementFixture::asked(), $storeManager), new CountWords(
                new EnglishPlurals()
            )),
            new GoogleFeatureRows(new KindPhrases([
                'home' => ['phrase' => 'the home page', 'singular' => true],
                'product' => ['phrase' => 'product pages'],
                'category' => ['phrase' => 'category pages'],
                'cms' => ['phrase' => 'CMS pages'],
                'other' => ['phrase' => 'every other page', 'singular' => true],
            ]), $words, new EnglishPlurals()),
            new AttentionRows($links),
            $times
        );
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
     * @param list<int> $storeIds
     * @return Snippet
     */
    private static function snippet(
        int $id,
        string $code,
        string $name,
        array $kinds,
        bool $active = true,
        ?ConditionTreeInterface $conditions = null,
        array $storeIds = [0]
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
            $storeIds,
            null,
            $id,
            '2026-11-13 09:00:00',
            'admin'
        );
    }

    /**
     * The condition of the Product snippet: not a configurable product
     *
     * @return ConditionTreeInterface
     */
    private static function notConfigurable(): ConditionTreeInterface
    {
        return PlacementFixture::leaves([['product_type', '!=', 'configurable']]);
    }

    /**
     * The condition of the Product with variants snippet: a configurable product
     *
     * @return ConditionTreeInterface
     */
    private static function configurable(): ConditionTreeInterface
    {
        return PlacementFixture::leaves([['product_type', '==', 'configurable']]);
    }

    /**
     * A contract file, decoded
     *
     * @param string $name
     * @return array<mixed>
     */
    private function contract(string $name): array
    {
        $decoded = json_decode((string) file_get_contents(self::CONTRACTS . $name), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded);

        return $decoded;
    }

    /**
     * An answer as it reaches the browser
     *
     * @param array<mixed> $answer
     * @return array<mixed>
     */
    private static function travelled(array $answer): array
    {
        $decoded = json_decode(
            (string) json_encode($answer, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION),
            true,
            512,
            JSON_THROW_ON_ERROR
        );
        self::assertIsArray($decoded);

        return $decoded;
    }
}
