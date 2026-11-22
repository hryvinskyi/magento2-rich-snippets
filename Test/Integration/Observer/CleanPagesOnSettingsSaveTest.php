<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Test\Integration\Observer;

use Magento\Config\Model\Config;
use Magento\Config\Model\Config\Factory as ConfigFactory;
use Magento\Framework\App\Cache\StateInterface;
use Magento\Framework\ObjectManagerInterface;
use Magento\PageCache\Model\Cache\Type as PageCache;
use Magento\Store\Model\StoreManagerInterface;
use Magento\TestFramework\Fixture\AppArea;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * Saving the snippets' settings cleans the cached pages of the store views the saved scope reaches, and no others
 *
 * Each test fills the built-in page cache with one entry tagged with the default store view and one tagged with a
 * store view the shop does not have, saves the settings through the platform's config model, and reads which
 * entries are left. Which store views a scope of several websites reaches is pinned by the scope's unit test.
 */
#[
    AppArea('adminhtml'),
    DbIsolation(true),
]
class CleanPagesOnSettingsSaveTest extends TestCase
{
    private const SECTION = 'hryvinskyi_rich_snippets';
    private const UNKNOWN_STORE_TAG = 'rich_snippets_store_987654';

    /**
     * @var ObjectManagerInterface
     */
    private ObjectManagerInterface $objectManager;

    /**
     * @var bool
     */
    private bool $pageCacheWasEnabled = false;

    /**
     * Switch the built-in page cache on, which only cleans by tags while it is enabled
     *
     * @return void
     */
    protected function setUp(): void
    {
        $this->objectManager = Bootstrap::getObjectManager();
        $state = $this->get(StateInterface::class);
        $this->pageCacheWasEnabled = $state->isEnabled(PageCache::TYPE_IDENTIFIER);
        $state->setEnabled(PageCache::TYPE_IDENTIFIER, true);
        $this->get(PageCache::class)->clean();
    }

    /**
     * @return void
     */
    protected function tearDown(): void
    {
        $this->get(PageCache::class)->clean();
        $this->get(StateInterface::class)->setEnabled(PageCache::TYPE_IDENTIFIER, $this->pageCacheWasEnabled);
    }

    public function testSavingTheDefaultScopeCleansEveryStoreView(): void
    {
        $this->assertSaveCleansTheDefaultStoreView(null, null);
    }

    public function testSavingAWebsiteCleansItsStoreViews(): void
    {
        $this->assertSaveCleansTheDefaultStoreView((string) $this->defaultWebsiteId(), null);
    }

    public function testSavingAStoreViewCleansThatStoreView(): void
    {
        $this->assertSaveCleansTheDefaultStoreView(null, (string) $this->defaultStoreId());
    }

    /**
     * Save at a scope that reaches the default store view and check only its pages were cleaned
     *
     * @param string|null $website
     * @param string|null $store
     * @return void
     */
    private function assertSaveCleansTheDefaultStoreView(?string $website, ?string $store): void
    {
        $tags = [$this->tag($this->defaultStoreId()), self::UNKNOWN_STORE_TAG];
        $this->cachePages($tags);

        $this->save($website, $store);

        self::assertSame([self::UNKNOWN_STORE_TAG], $this->cachedPages($tags));
    }

    /**
     * Save the General group of the section at a scope, as the admin's settings page does
     *
     * @param string|null $website
     * @param string|null $store
     * @return void
     */
    private function save(?string $website, ?string $store): void
    {
        $config = $this->get(ConfigFactory::class)->create([
            'data' => [
                'section' => self::SECTION,
                'website' => $website,
                'store' => $store,
                'groups' => ['general' => ['fields' => ['enabled' => ['value' => '0']]]],
            ],
        ]);
        self::assertInstanceOf(Config::class, $config);
        $config->save();
    }

    /**
     * The page cache tag of a store view
     *
     * @param int $storeId
     * @return string
     */
    private function tag(int $storeId): string
    {
        return 'rich_snippets_store_' . $storeId;
    }

    /**
     * Store one page cache entry per tag
     *
     * @param list<string> $tags
     * @return void
     */
    private function cachePages(array $tags): void
    {
        foreach ($tags as $tag) {
            $this->get(PageCache::class)->save('page of ' . $tag, 'rs_test_' . $tag, [$tag]);
        }
    }

    /**
     * The tags whose page cache entry is still stored, in the order given
     *
     * @param list<string> $tags
     * @return list<string>
     */
    private function cachedPages(array $tags): array
    {
        return array_values(array_filter(
            $tags,
            fn (string $tag): bool => $this->get(PageCache::class)->load('rs_test_' . $tag) !== false
        ));
    }

    /**
     * The default store view's ID
     *
     * @return int
     */
    private function defaultStoreId(): int
    {
        return (int) $this->get(StoreManagerInterface::class)->getDefaultStoreView()?->getId();
    }

    /**
     * The default store view's website ID
     *
     * @return int
     */
    private function defaultWebsiteId(): int
    {
        return (int) $this->get(StoreManagerInterface::class)->getDefaultStoreView()?->getWebsiteId();
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
