<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Test\Integration\Controller\Adminhtml;

use Hryvinskyi\RichSnippets\Api\Data\CheckTrigger;
use Hryvinskyi\RichSnippets\Api\Data\ThemeMarkupObservationInterface;
use Hryvinskyi\RichSnippets\Api\ThemeMarkupObservationsInterface;
use Hryvinskyi\RichSnippets\Model\Check\AggregateMetric;
use Hryvinskyi\RichSnippets\Model\Check\CheckResultWriterInterface;
use Hryvinskyi\RichSnippets\Model\Check\CheckRunStoreInterface;
use Hryvinskyi\RichSnippets\Model\Check\Storage\CheckTables;
use Hryvinskyi\RichSnippets\Model\Html\MicrodataItem;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\App\Response\Http as HttpResponse;
use Magento\Store\Model\StoreManagerInterface;
use Magento\TestFramework\Fixture\Config;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\ObjectManager;
use Magento\TestFramework\TestCase\AbstractBackendController;

/**
 * The settings section as Stores › Configuration draws it for an administrator
 *
 * The read-only lines read what page tests saw and what catalog checks counted; each test that depends on them starts
 * from none, inside its own transaction, whatever the database kept.
 */
class SettingsPageTest extends AbstractBackendController
{
    private const OBSERVATION_TABLE = 'hryvinskyi_rich_snippets_observation';
    private const REMOVE_THEME_MARKUP = 'hryvinskyi_rich_snippets/theme/remove_product_markup';

    /**
     * @var string
     */
    protected $resource = 'Hryvinskyi_RichSnippets::config';

    /**
     * @var string
     */
    protected $uri = 'backend/admin/system_config/edit/section/hryvinskyi_rich_snippets';

    /**
     * Stores › Configuration sends an administrator without the section's permission on to a section they may see
     *
     * @var int
     */
    protected $expectedNoAccessResponseCode = 302;

    public function testTheSectionDrawsEveryFieldsetWithItsWording(): void
    {
        $body = $this->page();

        foreach ([
            'Snippets are added, changed and switched on in Marketing › Rich Snippets.',
            'Write the Snippets',
            'At the end of the body',
            'As the catalog shows them',
            'The default theme and the themes built on it write their own Product markup into the product page.',
            'Only on product pages that output a Product snippet.',
            'Check the Catalog Every Night',
            'Mark Each Block in the Page Source',
        ] as $text) {
            self::assertStringContainsString($text, $body);
        }
    }

    #[DbIsolation(true)]
    public function testWithNothingSeenTheLinesSayTheMarkupIsRemovedAndNoCheckHasRun(): void
    {
        $this->forgetWhatWasSeen();

        $body = $this->page();

        self::assertStringContainsString(
            'rich-snippets-status--ok"><span class="rich-snippets-status__dot" aria-hidden="true"></span>'
                . 'Nothing. The theme’s Product markup is removed; each product is described once, by the Product'
                . ' snippet.',
            $body
        );
        self::assertStringContainsString('No check has run yet.', $body);
    }

    #[DbIsolation(true)]
    #[Config(self::REMOVE_THEME_MARKUP, '0', 'store', 'default')]
    public function testWithRemovalOffAndNoPageTestTheLineSaysNothingWasLookedAt(): void
    {
        $this->forgetWhatWasSeen();

        $body = $this->page();

        self::assertStringContainsString(
            'rich-snippets-status--none"><span class="rich-snippets-status__dot" aria-hidden="true"></span>'
                . 'No page test has looked at a product page yet.',
            $body
        );
    }

    #[DbIsolation(true)]
    public function testMarkupSeenOnTheLastProductPageReadIsNamedWithWhereItWasSeen(): void
    {
        $this->forgetWhatWasSeen();
        $observations = $this->testObjectManager()->get(ThemeMarkupObservationsInterface::class);
        self::assertInstanceOf(ThemeMarkupObservationsInterface::class, $observations);
        $item = $this->testObjectManager()->create(
            MicrodataItem::class,
            ['type' => 'Product', 'properties' => ['name', 'offers']]
        );
        $observation = $this->testObjectManager()->create(ThemeMarkupObservationInterface::class, [
            'storeId' => $this->storeId(),
            'kind' => 'product',
            'path' => '/check-lamp.html',
            'items' => [$item],
            'seenAt' => time() - 3600,
        ]);
        self::assertInstanceOf(ThemeMarkupObservationInterface::class, $observation);
        $observations->record($observation);

        $body = $this->page();

        self::assertMatchesRegularExpression(
            '#rich-snippets-status--warn"><span class="rich-snippets-status__dot" aria-hidden="true"></span>'
                . 'Product markup in the theme’s HTML: name and offers\\. Seen [^<]+ on /check-lamp\\.html\\.</p>#u',
            $body
        );
    }

    #[DbIsolation(true)]
    public function testTheLastCheckIsSummedUpWithALinkToItsFindings(): void
    {
        $this->forgetWhatWasSeen();
        $runs = $this->testObjectManager()->get(CheckRunStoreInterface::class);
        self::assertInstanceOf(CheckRunStoreInterface::class, $runs);
        $started = time() - 7200;
        $runId = $runs->request(CheckTrigger::Schedule, $started)->getId();
        $runs->claim($runId, $started);
        $runs->start($runId, 1240, 1, $started);
        $runs->progress($runId, 1240, $started + 130);
        $runs->finish($runId, $started + 130);
        $results = $this->testObjectManager()->get(CheckResultWriterInterface::class);
        self::assertInstanceOf(CheckResultWriterInterface::class, $results);
        $results->writeAggregates($runId, 0, [
            ['metric' => AggregateMetric::Pages, 'subject' => 'product', 'value' => 1240],
            ['metric' => AggregateMetric::InvalidPages, 'subject' => 'product', 'value' => 12],
            ['metric' => AggregateMetric::GapPages, 'subject' => 'product', 'value' => 30],
            ['metric' => AggregateMetric::GapPages, 'subject' => 'cms', 'value' => 4],
        ]);

        $body = $this->page();

        self::assertStringNotContainsString('No check has run yet.', $body);
        self::assertMatchesRegularExpression(
            '#<td>1240</td><td class="rich-snippets-last-check__value rich-snippets-last-check__value--invalid">12</td>'
                . '<td>34</td>#',
            $body
        );
        self::assertStringContainsString('in 2 minutes.', $body);
        self::assertMatchesRegularExpression(
            '#<a href="[^"]*richsnippets/test/index/[^"]*mode=catalog[^"]*">Open the findings</a>#',
            $body
        );
    }

    public function testTheCheckFieldsCarryTheirBrowserRulesAndExamples(): void
    {
        $body = $this->page();

        $time = $this->input('hryvinskyi_rich_snippets_check_time', $body);
        self::assertStringContainsString('validate-rich-snippets-time', $time);
        self::assertStringContainsString('placeholder="03:00"', $time);
        $email = $this->input('hryvinskyi_rich_snippets_check_email', $body);
        self::assertStringContainsString('validate-emails', $email);
        self::assertStringContainsString('placeholder="seo@example.com"', $email);
    }

    /**
     * Forget every page test's observation and every catalog check, for the rest of the test
     *
     * @return void
     */
    private function forgetWhatWasSeen(): void
    {
        $resource = $this->testObjectManager()->get(ResourceConnection::class);
        self::assertInstanceOf(ResourceConnection::class, $resource);
        $connection = $resource->getConnection();
        $connection->delete($resource->getTableName(self::OBSERVATION_TABLE));
        $connection->delete($resource->getTableName(CheckTables::RUN));
    }

    /**
     * The default store view's ID
     *
     * @return int
     */
    private function storeId(): int
    {
        $stores = $this->testObjectManager()->get(StoreManagerInterface::class);
        self::assertInstanceOf(StoreManagerInterface::class, $stores);

        return (int) $stores->getDefaultStoreView()?->getId();
    }

    /**
     * The test's object manager
     *
     * @return ObjectManager
     */
    private function testObjectManager(): ObjectManager
    {
        $objectManager = Bootstrap::getObjectManager();
        self::assertInstanceOf(ObjectManager::class, $objectManager);

        return $objectManager;
    }

    /**
     * The section's page as an administrator gets it
     *
     * @return string
     */
    private function page(): string
    {
        $this->dispatch($this->uri);
        $response = $this->getResponse();
        self::assertInstanceOf(HttpResponse::class, $response);

        return (string) $response->getBody();
    }

    /**
     * The input element with an id
     *
     * @param string $id
     * @param string $body
     * @return string
     */
    private function input(string $id, string $body): string
    {
        $found = preg_match('/<input[^>]*id="' . preg_quote($id, '/') . '"[^>]*>/', $body, $match);
        self::assertSame(1, $found, 'The page has no input ' . $id . '.');

        return $match[0];
    }
}
