<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Test\Integration\Model\Overview;

use Hryvinskyi\RichSnippets\Api\CatalogCheckInterface;
use Hryvinskyi\RichSnippets\Api\Data\AttentionItemInterface;
use Hryvinskyi\RichSnippets\Api\Data\CheckTrigger;
use Hryvinskyi\RichSnippets\Api\Data\FeatureOverviewInterface;
use Hryvinskyi\RichSnippets\Api\Data\KindOverviewInterface;
use Hryvinskyi\RichSnippets\Api\Data\KindSnippetInterface;
use Hryvinskyi\RichSnippets\Api\Data\OverviewReportInterface;
use Hryvinskyi\RichSnippets\Api\Data\SetupState;
use Hryvinskyi\RichSnippets\Api\Data\TallyStatus;
use Hryvinskyi\RichSnippets\Api\Data\ThemeMarkupState;
use Hryvinskyi\RichSnippets\Api\OverviewInterface;
use Hryvinskyi\RichSnippets\Api\SnippetManagementInterface;
use Hryvinskyi\RichSnippets\Model\Cache\Type\RichSnippetsCache;
use Hryvinskyi\RichSnippets\Model\Check\AggregateMetric;
use Hryvinskyi\RichSnippets\Model\Check\CheckResultWriterInterface;
use Hryvinskyi\RichSnippets\Model\Check\CheckRunStoreInterface;
use Hryvinskyi\RichSnippets\Model\Check\RequestedChecks;
use Hryvinskyi\RichSnippets\Model\Check\Storage\CheckTables;
use Hryvinskyi\RichSnippets\Model\ResourceModel\Snippet as SnippetResource;
use Hryvinskyi\RichSnippets\Model\Selection\SelectionIndexInterface;
use Hryvinskyi\RichSnippets\Test\Integration\Fixture\StoresTestSnippets;
use Magento\Catalog\Test\Fixture\Virtual as VirtualProductFixture;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\ObjectManager\ResetAfterRequestInterface;
use Magento\Framework\ObjectManagerInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\TestFramework\Fixture\AppArea;
use Magento\TestFramework\Fixture\AppIsolation;
use Magento\TestFramework\Fixture\Config;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * The Overview read through the real container over stored snippets, the shop's pages and catalog checks: nothing
 * set up, all off, live; a store view that hides a snippet set for another; the Google results of a seeded check;
 * and the things that want fixing, which come and go with their causes
 */
#[
    AppArea('adminhtml'),
    AppIsolation(true),
    DbIsolation(true),
    DataFixture(VirtualProductFixture::class, ['sku' => 'rs-overview-virtual'], as: 'product'),
]
class OverviewTest extends TestCase
{
    use StoresTestSnippets;

    private const OTHER_STORE = 999;
    private const PRODUCT_TEMPLATE = [
        '@context' => 'https://schema.org',
        '@type' => 'Product',
        'name' => '{{product.name}}',
        'image' => '{{product.image}}',
        'offers' => ['@type' => 'Offer', 'price' => '10.00', 'priceCurrency' => 'EUR'],
    ];

    /**
     * @var ObjectManagerInterface
     */
    private ObjectManagerInterface $objectManager;

    /**
     * Start every test with no snippet, no run and no page test, whatever the database kept
     *
     * @return void
     */
    protected function setUp(): void
    {
        $this->objectManager = Bootstrap::getObjectManager();
        $resource = $this->get(ResourceConnection::class);
        $connection = $resource->getConnection();
        $connection->delete($resource->getTableName(CheckTables::RUN));
        $connection->delete($resource->getTableName(SnippetResource::TABLE));
        $connection->delete($resource->getTableName('hryvinskyi_rich_snippets_observation'));
        $this->get(RichSnippetsCache::class)->clean();
    }

    /**
     * @return void
     */
    protected function tearDown(): void
    {
        $this->get(RichSnippetsCache::class)->clean();
    }

    #[Config('hryvinskyi_rich_snippets/theme/remove_product_markup', '0', 'store', 'default')]
    public function testAShopWithNoSnippetHasNothingSetUpAndNothingToFix(): void
    {
        $report = $this->overview()->read(null);

        self::assertSame(SetupState::None, $report->getSetupState());
        self::assertSame([0, 0, null], [$report->getSnippetsAdded(), $report->getSnippetsOn(), $report->getLastRun()]);
        self::assertSame(['home', 'product', 'category', 'cms', 'other'], array_map(
            static fn (KindOverviewInterface $kind): string => $kind->getKind(),
            $report->getKinds()
        ));
        self::assertNull($this->kind($report, 'other')->getPageCount(), 'Every other page cannot be listed.');
        self::assertGreaterThanOrEqual(1, $this->kind($report, 'product')->getPageCount());
        self::assertSame([], $report->getAttentionItems(), 'Nothing is flagged before anything is set up.');
    }

    #[Config('hryvinskyi_rich_snippets/theme/remove_product_markup', '0', 'store', 'default')]
    public function testSnippetsAddedAndAllOffAreOffWithOnlyTheThemeMarkupToFix(): void
    {
        $this->storeSnippet('rs-overview-product', 'Product', self::PRODUCT_TEMPLATE, ['kinds' => ['product']]);
        $this->storeSnippet('rs-overview-crumbs', 'Breadcrumbs', [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'name' => 'Path',
        ], ['kinds' => ['product', 'category']]);

        $report = $this->overview()->read(null);

        self::assertSame(SetupState::Off, $report->getSetupState());
        self::assertSame([2, 0, []], [$report->getSnippetsAdded(), $report->getSnippetsOn(), $report->getKindsOn()]);
        self::assertNotNull($report->getFirstAddedAt());
        self::assertSame(ThemeMarkupState::StillThere, $report->getThemeMarkup());
        self::assertSame(['theme-markup'], $this->keys($report));
        self::assertSame(['Product', 'Breadcrumbs'], $this->names($this->kind($report, 'product')));
        self::assertSame([false, false], array_map(
            static fn (KindSnippetInterface $set): bool => $set->getSnippet()->isActive(),
            $this->kind($report, 'product')->getSnippets()
        ));
    }

    public function testASwitchedOnSnippetWithAKindOfPageIsLive(): void
    {
        $this->storeSnippet('rs-overview-product', 'Product', self::PRODUCT_TEMPLATE, [
            'kinds' => ['product'],
            'conditions' => [['product_type', '==', 'virtual']],
            'active' => true,
        ]);

        $report = $this->overview()->read(null);

        self::assertSame(SetupState::Live, $report->getSetupState());
        self::assertSame([1, 1], [$report->getSnippetsAdded(), $report->getSnippetsOn()]);
        self::assertSame(['product'], $report->getKindsOn());
        self::assertSame([$this->storeId()], $report->getStoreIdsOn());
        self::assertSame(ThemeMarkupState::Removed, $report->getThemeMarkup(), 'The module takes it out by default.');
        $count = $this->kind($report, 'product')->getSnippets()[0]->getPlacementCount();
        self::assertNotNull($count, 'A snippet with conditions counts the pages that meet them.');
        self::assertGreaterThanOrEqual(1, $count->getMatching());
        self::assertNotNull($this->kind($report, 'product')->getSampleEntityId());
    }

    public function testAStoreViewHidesASnippetSetForAnotherStoreView(): void
    {
        $this->storeSnippet('rs-overview-product', 'Product', self::PRODUCT_TEMPLATE, [
            'kinds' => ['product'],
            'active' => true,
        ]);
        $this->storeSnippet('rs-overview-elsewhere', 'Elsewhere', self::PRODUCT_TEMPLATE, [
            'kinds' => ['product'],
            'stores' => [self::OTHER_STORE],
            'active' => true,
        ]);

        $one = $this->overview()->read($this->storeId());
        $every = $this->overview()->read(null);

        self::assertSame(['Product'], $this->names($this->kind($one, 'product')));
        self::assertSame([1, 1], [$one->getSnippetsAdded(), $one->getSnippetsOn()]);
        self::assertSame(['Product', 'Elsewhere'], $this->names($this->kind($every, 'product')));
        self::assertSame([2, 2], [$every->getSnippetsAdded(), $every->getSnippetsOn()]);
        self::assertSame([$this->storeId()], $every->getStoreIdsOn(), 'Only the shop\'s own store views count.');
    }

    public function testAStoreViewTheShopDoesNotHaveIsRefused(): void
    {
        $this->expectException(NoSuchEntityException::class);

        $this->overview()->read(self::OTHER_STORE);
    }

    public function testTheGoogleResultsAndInvalidPagesComeFromTheLastCheckOfTheScope(): void
    {
        $this->storeSnippet('rs-overview-product', 'Product', self::PRODUCT_TEMPLATE, [
            'kinds' => ['product'],
            'active' => true,
        ]);
        $this->seedRun([
            ['metric' => AggregateMetric::PagesWithSnippets, 'subject' => '', 'value' => 40],
            ['metric' => AggregateMetric::InvalidPages, 'subject' => 'product', 'value' => 3],
            ['metric' => AggregateMetric::FeatureMet, 'subject' => 'merchant_listing', 'value' => 37],
            ['metric' => AggregateMetric::FeatureNotMet, 'subject' => 'merchant_listing', 'value' => 3],
        ], [
            ['metric' => AggregateMetric::PagesWithSnippets, 'subject' => '', 'value' => 90],
            ['metric' => AggregateMetric::InvalidPages, 'subject' => 'product', 'value' => 5],
            ['metric' => AggregateMetric::FeatureMet, 'subject' => 'merchant_listing', 'value' => 85],
            ['metric' => AggregateMetric::FeatureNotMet, 'subject' => 'merchant_listing', 'value' => 5],
            ['metric' => AggregateMetric::FeatureMet, 'subject' => 'faq', 'value' => 2],
        ]);

        $one = $this->overview()->read($this->storeId());
        $every = $this->overview()->read(null);

        self::assertSame(40, $one->getPagesWithSnippets());
        self::assertSame(['product' => 3], $one->getInvalidPagesByKind());
        self::assertSame([['merchant_listing', 37, 40, TallyStatus::Partly, ['product']]], $this->features($one));
        self::assertSame(90, $every->getPagesWithSnippets());
        self::assertSame([
            ['merchant_listing', 85, 90, TallyStatus::Partly, ['product']],
            ['faq', 2, 2, TallyStatus::NoResult, []],
        ], $this->features($every));
    }

    public function testWhileEverySnippetIsOffTheLastCheckCountsNothing(): void
    {
        $this->storeSnippet('rs-overview-product', 'Product', self::PRODUCT_TEMPLATE, ['kinds' => ['product']]);
        $this->seedRun([], [
            ['metric' => AggregateMetric::PagesWithSnippets, 'subject' => '', 'value' => 90],
            ['metric' => AggregateMetric::InvalidPages, 'subject' => 'product', 'value' => 5],
            ['metric' => AggregateMetric::FeatureMet, 'subject' => 'merchant_listing', 'value' => 85],
        ]);

        $report = $this->overview()->read(null);

        self::assertSame(SetupState::Off, $report->getSetupState());
        self::assertSame([0, [], []], [
            $report->getPagesWithSnippets(),
            $report->getInvalidPagesByKind(),
            $report->getFeatures(),
        ]);
    }

    public function testTheGapsOfTheLastCheckAreListedWhileLiveAndGoWhenTheSnippetIsSwitchedOff(): void
    {
        $snippet = $this->storeSnippet('rs-overview-product', 'Product', self::PRODUCT_TEMPLATE + [
            'description' => '{{product.short_description}}',
        ], [
            'kinds' => ['product'],
            'conditions' => [['product_type', '==', 'virtual']],
            'active' => true,
        ]);
        $this->checkTheCatalog();

        $live = $this->overview()->read(null);
        $this->get(SnippetManagementInterface::class)->setActive((int) $snippet->getId(), false);
        $this->resetSelection();
        $off = $this->overview()->read(null);

        $image = $this->item($live, 'finding-merchant-listing-image-product-image');
        self::assertSame('bad', $image->getTone()->value);
        self::assertSame(
            ['mode' => 'catalog', 'finding' => 'merchant-listing-image-product-image'],
            $image->getTarget()->getParams()
        );
        $description = $this->item($live, 'finding-merchant-listing-description-product-short-description');
        self::assertSame('warn', $description->getTone()->value, 'An empty value the template reads is listed.');
        self::assertNotContains(
            'finding-merchant-listing-sku',
            $this->keys($live),
            'A recommended property the template never writes leaves the catalog nothing to fill.'
        );
        self::assertSame(
            [],
            $this->keys($off),
            'With the snippet off nothing is on a page; the module removes the theme markup.'
        );
    }

    #[Config('hryvinskyi_rich_snippets/theme/remove_product_markup', '0', 'store', 'default')]
    public function testTheThemeMarkupIsFlaggedWhileTheSettingsKeepIt(): void
    {
        $this->storeSnippet('rs-overview-product', 'Product', self::PRODUCT_TEMPLATE, [
            'kinds' => ['product'],
            'active' => true,
        ]);

        $report = $this->overview()->read($this->storeId());

        self::assertSame(['theme-markup'], $this->keys($report));
        self::assertSame(
            ['section' => 'hryvinskyi_rich_snippets', 'store' => (string) $this->storeId()],
            $this->item($report, 'theme-markup')->getTarget()->getParams()
        );
    }

    /**
     * Store a finished run with counts of the default store view and of every store view
     *
     * @param list<array{metric: AggregateMetric,subject: string,value: int}> $storeRows
     * @param list<array{metric: AggregateMetric,subject: string,value: int}> $allRows
     * @return void
     */
    private function seedRun(array $storeRows, array $allRows): void
    {
        $runs = $this->get(CheckRunStoreInterface::class);
        $started = time() - 600;
        $runId = $runs->request(CheckTrigger::Schedule, $started)->getId();
        $runs->claim($runId, $started);
        $runs->start($runId, 90, 1, $started);
        $runs->finish($runId, $started + 40);
        $results = $this->get(CheckResultWriterInterface::class);
        $results->writeAggregates($runId, $this->storeId(), $storeRows);
        $results->writeAggregates($runId, 0, $allRows);
    }

    /**
     * Ask for a check and run it as the minute job does
     *
     * @return void
     */
    private function checkTheCatalog(): void
    {
        $this->get(RichSnippetsCache::class)->clean();
        $this->get(CatalogCheckInterface::class)->request(CheckTrigger::Manual);
        self::assertNotNull($this->get(RequestedChecks::class)->runNext(), 'The requested check ran.');
    }

    /**
     * Forget what this process remembers of the selection, as the commit of a switch would
     *
     * @return void
     */
    private function resetSelection(): void
    {
        $this->get(RichSnippetsCache::class)->clean();
        $index = $this->objectManager->get(SelectionIndexInterface::class);
        self::assertInstanceOf(ResetAfterRequestInterface::class, $index);
        $index->_resetState();
    }

    /**
     * The kind of page of a report with a code
     *
     * @param OverviewReportInterface $report
     * @param string $code
     * @return KindOverviewInterface
     */
    private function kind(OverviewReportInterface $report, string $code): KindOverviewInterface
    {
        foreach ($report->getKinds() as $kind) {
            if ($kind->getKind() === $code) {
                return $kind;
            }
        }
        self::fail(sprintf('The report has no kind "%s".', $code));
    }

    /**
     * The names of the snippets set for a kind of page, in order
     *
     * @param KindOverviewInterface $kind
     * @return list<string>
     */
    private function names(KindOverviewInterface $kind): array
    {
        return array_map(
            static fn (KindSnippetInterface $set): string => $set->getSnippet()->getName(),
            $kind->getSnippets()
        );
    }

    /**
     * Each Google result of a report: its rule, met, total, status and kinds of page
     *
     * @param OverviewReportInterface $report
     * @return list<array{string,int,int,TallyStatus,list<string>}>
     */
    private function features(OverviewReportInterface $report): array
    {
        return array_map(static fn (FeatureOverviewInterface $feature): array => [
            $feature->getTally()->getRule()->getCode(),
            $feature->getTally()->getMet(),
            $feature->getTally()->getTotal(),
            $feature->getTally()->getStatus(),
            $feature->getPageKinds(),
        ], $report->getFeatures());
    }

    /**
     * The keys of a report's attention items, in order
     *
     * @param OverviewReportInterface $report
     * @return list<string>
     */
    private function keys(OverviewReportInterface $report): array
    {
        return array_map(
            static fn (AttentionItemInterface $item): string => $item->getKey(),
            $report->getAttentionItems()
        );
    }

    /**
     * A report's attention item with a key
     *
     * @param OverviewReportInterface $report
     * @param string $key
     * @return AttentionItemInterface
     */
    private function item(OverviewReportInterface $report, string $key): AttentionItemInterface
    {
        foreach ($report->getAttentionItems() as $item) {
            if ($item->getKey() === $key) {
                return $item;
            }
        }
        self::fail(sprintf('No attention item has the key "%s".', $key));
    }

    /**
     * @return OverviewInterface
     */
    private function overview(): OverviewInterface
    {
        return $this->get(OverviewInterface::class);
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
