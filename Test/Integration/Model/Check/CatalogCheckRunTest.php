<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Test\Integration\Model\Check;

use Hryvinskyi\RichSnippets\Api\CatalogCheckInterface;
use Hryvinskyi\RichSnippets\Api\Data\CheckRunInterface;
use Hryvinskyi\RichSnippets\Api\Data\CheckState;
use Hryvinskyi\RichSnippets\Api\Data\CheckTrigger;
use Hryvinskyi\RichSnippets\Api\Data\FindingInterface;
use Hryvinskyi\RichSnippets\Api\Data\SnippetFlagInterface;
use Hryvinskyi\RichSnippets\Api\SnippetListInterface;
use Hryvinskyi\RichSnippets\Model\Admin\Check\CheckAnswers;
use Hryvinskyi\RichSnippets\Model\Cache\Type\RichSnippetsCache;
use Hryvinskyi\RichSnippets\Model\Check\RequestedChecks;
use Hryvinskyi\RichSnippets\Model\Check\Storage\CheckTables;
use Hryvinskyi\RichSnippets\Test\Integration\Fixture\StoresTestSnippets;
use Magento\Catalog\Test\Fixture\Attribute as AttributeFixture;
use Magento\Catalog\Test\Fixture\Virtual as VirtualProductFixture;
use Magento\Cms\Test\Fixture\Page as PageFixture;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DataObject;
use Magento\Framework\Mail\EmailMessageInterface;
use Magento\Framework\Model\AbstractModel;
use Magento\Framework\ObjectManagerInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\TestFramework\Fixture\AppArea;
use Magento\TestFramework\Fixture\AppIsolation;
use Magento\TestFramework\Fixture\Config;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Fixture\DataFixtureStorageManager;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\Mail\Template\TransportBuilderMock;
use PHPUnit\Framework\TestCase;

/**
 * A whole catalog check over the shop's pages: what it finds on the pages a snippet writes, how many pages each gap
 * is on per store view and over every store view, which pages they are, and the e-mail it sends when pages are
 * invalid
 *
 * The product snippet is placed on virtual products only, so only the fixtures' pages carry blocks; every other page
 * of the shop is counted as checked and found clean. The offer's price is written into the template, so the check
 * needs no price index. The attribute fixture is made again for each test under a new ID, so the application is
 * reset after each test rather than keep the attribute it read before.
 */
#[
    AppArea('adminhtml'),
    AppIsolation(true),
    DbIsolation(true),
    DataFixture(AttributeFixture::class, ['attribute_code' => 'rs_check_ean', 'default_frontend_label' => 'EAN']),
    DataFixture(VirtualProductFixture::class, ['sku' => 'rs-check-lamp', 'name' => 'Check Lamp'], as: 'lamp'),
    DataFixture(
        VirtualProductFixture::class,
        [
            'sku' => 'rs-check-shade',
            'name' => 'Check Shade',
            'short_description' => 'A linen shade.',
            'rs_check_ean' => '4062001000010',
        ],
        as: 'shade'
    ),
    DataFixture(
        VirtualProductFixture::class,
        ['sku' => 'rs-check-cord', 'name' => 'Check Cord', 'short_description' => 'A braided cord.'],
        as: 'cord'
    ),
    DataFixture(PageFixture::class, ['identifier' => 'rs-check-guide', 'title' => 'Lamp guide RS'], as: 'guide'),
]
class CatalogCheckRunTest extends TestCase
{
    use StoresTestSnippets;

    private const PRODUCT_SNIPPET = 'rs-check-product';
    private const PAGE_SNIPPET = 'rs-check-guide';
    private const NO_IMAGE = 'No image';
    private const NO_SHORT_DESCRIPTION = 'Short description is empty';
    private const NO_EAN = 'EAN is empty';
    private const NO_REVIEWS = 'Review stars left out';

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
        $this->get(TransportBuilderMock::class)->clean();
        $resource = $this->get(ResourceConnection::class);
        $resource->getConnection()->delete($resource->getTableName(CheckTables::RUN));
    }

    /**
     * @return void
     */
    protected function tearDown(): void
    {
        $this->get(RichSnippetsCache::class)->clean();
    }

    public function testACheckFindsEachGapOnThePagesThatHaveItInEveryStoreView(): void
    {
        $this->storeTheSnippets();

        $run = $this->checkTheCatalog();

        self::assertSame(CheckState::Done, $run->getState());
        self::assertSame(CheckTrigger::Manual, $run->getTrigger());
        self::assertGreaterThan(4, $run->getPagesTotal());
        self::assertSame($run->getPagesTotal(), $run->getPagesChecked());
        foreach ([$this->storeId(), null] as $storeId) {
            $rows = $this->rowsByTitle($storeId);
            self::assertSame(
                [self::NO_IMAGE, self::NO_EAN, self::NO_SHORT_DESCRIPTION],
                array_values(array_intersect(
                    array_keys($rows),
                    [self::NO_IMAGE, self::NO_EAN, self::NO_SHORT_DESCRIPTION]
                )),
                'The invalid gap comes first, then the gaps by the number of their pages.'
            );
            self::assertSame(['bad', 3, '3 products'], $this->summary($rows[self::NO_IMAGE] ?? null));
            self::assertSame(['warn', 2, '2 products'], $this->summary($rows[self::NO_EAN] ?? null));
            self::assertSame(['warn', 1, '1 product'], $this->summary($rows[self::NO_SHORT_DESCRIPTION] ?? null));
            self::assertSame(['note', 4, '4 pages'], $this->summary($rows[self::NO_REVIEWS] ?? null));
        }
        $status = $this->get(CatalogCheckInterface::class)->status();
        self::assertSame(3, $status->getInvalidPagesByKind()['product'] ?? null);
        self::assertSame(1, $status->getGapPagesByKind()['cms'] ?? null, 'The guide has gaps and nothing invalid.');
        self::assertSame(1, array_sum($status->getGapPagesByKind()), 'Every product page is invalid.');
    }

    public function testThePagesBehindAFindingAreTheProductsThatHaveTheGap(): void
    {
        $this->storeTheSnippets();
        $this->checkTheCatalog();
        $finding = $this->finding(self::NO_EAN);

        $items = $this->get(CatalogCheckInterface::class)->findingItems($finding->getKey(), $this->storeId(), 1);

        self::assertSame(2, $items->getTotal());
        self::assertSame(['product' => 2], $items->getFinding()->getPagesByKind());
        $listed = [];
        foreach ($items->getItems() as $item) {
            $listed[] = [$item->getKind(), $item->getEntityId(), $item->getLabel(), $item->getSku()];
        }
        self::assertEqualsCanonicalizing(
            [
                ['product', $this->id('lamp'), 'Check Lamp', 'rs-check-lamp'],
                ['product', $this->id('cord'), 'Check Cord', 'rs-check-cord'],
            ],
            $listed
        );
        $csv = $this->get(CatalogCheckInterface::class)->exportCsv($finding->getKey(), null);
        self::assertSame(2, $csv->getRows());
        self::assertStringContainsString('rs-check-lamp', $csv->getContent());
        self::assertStringNotContainsString('rs-check-shade', $csv->getContent());
    }

    public function testTheSnippetListFlagsTheSnippetWithTheGapsOfItsPages(): void
    {
        $this->storeTheSnippets();
        $this->checkTheCatalog();

        $flags = [];
        foreach ($this->get(SnippetListInterface::class)->list(null)->getRows() as $row) {
            $flags[$row->getSnippet()->getCode()] = array_map(
                static fn (SnippetFlagInterface $flag): string => $flag->getText(),
                $row->getFlags()
            );
        }

        self::assertContains('No image for 3 products', $flags[self::PRODUCT_SNIPPET] ?? []);
        self::assertContains('EAN is empty for 2 products', $flags[self::PRODUCT_SNIPPET] ?? []);
        self::assertNotContains('Review stars left out for 3 products', $flags[self::PRODUCT_SNIPPET] ?? []);
    }

    #[Config('hryvinskyi_rich_snippets/check/email', 'seo@example.com, catalog@example.com')]
    public function testACheckThatFindsInvalidPagesMailsTheRecipients(): void
    {
        $this->storeTheSnippets();

        $this->checkTheCatalog();

        $message = $this->get(TransportBuilderMock::class)->getSentMessage();
        self::assertInstanceOf(EmailMessageInterface::class, $message, 'The findings e-mail was sent.');
        $to = [];
        foreach ($message->getTo() as $address) {
            $to[] = $address->getEmail();
        }
        self::assertSame(['seo@example.com', 'catalog@example.com'], $to);
        $body = quoted_printable_decode($message->getBodyText());
        self::assertStringContainsString('found 3 pages', $body);
        self::assertStringContainsString('mode=catalog', $body);
    }

    #[Config('hryvinskyi_rich_snippets/check/email', 'seo@example.com')]
    public function testACheckThatFindsNoInvalidPageSendsNothing(): void
    {
        $this->checkTheCatalog();

        self::assertNull($this->get(TransportBuilderMock::class)->getSentMessage());
        self::assertSame([], $this->get(CatalogCheckInterface::class)->findings(null));
    }

    /**
     * Store the product snippet for virtual products and a snippet for the guide page
     *
     * @return void
     */
    private function storeTheSnippets(): void
    {
        $this->storeSnippet(self::PRODUCT_SNIPPET, 'Check product', [
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => '{{product.name}}',
            'image' => '{{product.image}}',
            'description' => '{{product.short_description}}',
            'sku' => '{{product.sku}}',
            'gtin13' => '{{product.rs_check_ean}}',
            'offers' => ['@type' => 'Offer', 'price' => '10.00', 'priceCurrency' => 'EUR'],
        ], ['kinds' => ['product'], 'conditions' => [['product_type', '==', 'virtual']], 'active' => true]);
        $this->storeSnippet(self::PAGE_SNIPPET, 'Check guide', [
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => 'Lamp guide',
            'image' => 'https://example.com/lamp.jpg',
            'offers' => ['@type' => 'Offer', 'price' => '10.00', 'priceCurrency' => 'EUR'],
        ], ['kinds' => ['cms'], 'conditions' => [['cms_page', '()', 'rs-check-guide']], 'active' => true]);
        $this->get(RichSnippetsCache::class)->clean();
    }

    /**
     * Ask for a check and run it as the minute job does
     *
     * @return CheckRunInterface
     */
    private function checkTheCatalog(): CheckRunInterface
    {
        $requested = $this->get(CatalogCheckInterface::class)->request(CheckTrigger::Manual);
        self::assertSame(CheckState::Requested, $requested->getState());

        $run = $this->get(RequestedChecks::class)->runNext();

        self::assertNotNull($run, 'The requested check ran.');
        self::assertSame($requested->getId(), $run->getId());

        return $run;
    }

    /**
     * The findings table the Test page shows, by title
     *
     * @param int|null $storeId
     * @return array<string, array<string, mixed>>
     */
    private function rowsByTitle(?int $storeId): array
    {
        $table = $this->get(CheckAnswers::class)->findings(
            $storeId === null ? [] : ['store' => (string) $storeId]
        );
        $rows = [];
        foreach ($table['findings'] as $row) {
            $rows[$row['title']] = $row;
        }

        return $rows;
    }

    /**
     * A row's tone, number of pages and count
     *
     * @param array<string, mixed>|null $row
     * @return array{mixed, mixed, mixed}
     */
    private function summary(?array $row): array
    {
        self::assertNotNull($row, 'The finding is listed.');

        return [$row['tone'] ?? null, $row['pages'] ?? null, $row['count'] ?? null];
    }

    /**
     * The finding of the last check with a title, over every store view
     *
     * @param string $title
     * @return FindingInterface
     */
    private function finding(string $title): FindingInterface
    {
        $key = $this->rowsByTitle(null)[$title]['key'] ?? null;
        self::assertIsString($key, $title . ' is found.');
        foreach ($this->get(CatalogCheckInterface::class)->findings(null) as $finding) {
            if ($finding->getKey() === $key) {
                return $finding;
            }
        }
        self::fail($title . ' is among the findings.');
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
     * A fixture's ID
     *
     * @param string $name
     * @return int
     */
    private function id(string $name): int
    {
        $fixture = DataFixtureStorageManager::getStorage()->get($name);
        self::assertInstanceOf(DataObject::class, $fixture);
        self::assertInstanceOf(AbstractModel::class, $fixture);
        $id = $fixture->getId();
        self::assertIsNumeric($id);

        return (int) $id;
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
