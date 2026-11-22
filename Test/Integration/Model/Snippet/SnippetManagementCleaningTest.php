<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Test\Integration\Model\Snippet;

use Hryvinskyi\RichSnippets\Api\Data\ImportChoice;
use Hryvinskyi\RichSnippets\Api\PageCacheTagInterface;
use Hryvinskyi\RichSnippets\Api\SnippetExportInterface;
use Hryvinskyi\RichSnippets\Api\SnippetImportInterface;
use Hryvinskyi\RichSnippets\Api\SnippetManagementInterface;
use Hryvinskyi\RichSnippets\Model\Cache\Type\RichSnippetsCache;
use Hryvinskyi\RichSnippets\Model\ResourceModel\IdempotencyEntry;
use Hryvinskyi\RichSnippets\Model\ResourceModel\Snippet as SnippetResource;
use Hryvinskyi\RichSnippets\Test\Integration\Fixture\StoresTestSnippets;
use Magento\Framework\App\Cache\StateInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\PageCache\Model\Cache\Type as PageCache;
use Magento\Store\Model\StoreManagerInterface;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * Switching a snippet, deleting it and replacing it by an import clean the cached pages they reach once committed
 *
 * The cleaning runs as a commit callback, which never fires inside the transaction a database-isolated test runs
 * in, so these tests commit for real and delete what they stored afterwards. Each test fills the built-in page cache
 * with one entry per tag it cares about and reads which entries are left.
 */
#[DbIsolation(false)]
class SnippetManagementCleaningTest extends TestCase
{
    use StoresTestSnippets;

    private const KEY_PREFIX = 'rs-clean-';

    /**
     * @var bool
     */
    private bool $pageCacheWasEnabled = false;

    /**
     * @var bool
     */
    private bool $moduleCacheWasEnabled = false;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        $state = $this->get(StateInterface::class);
        $this->pageCacheWasEnabled = $state->isEnabled(PageCache::TYPE_IDENTIFIER);
        $state->setEnabled(PageCache::TYPE_IDENTIFIER, true);
        $this->moduleCacheWasEnabled = $state->isEnabled(RichSnippetsCache::TYPE_IDENTIFIER);
        $state->setEnabled(RichSnippetsCache::TYPE_IDENTIFIER, true);
        $this->deleteTestRows();
        $this->get(RichSnippetsCache::class)->clean();
    }

    /**
     * @return void
     */
    protected function tearDown(): void
    {
        $this->deleteTestRows();
        $this->get(PageCache::class)->clean();
        $this->get(RichSnippetsCache::class)->clean();
        $state = $this->get(StateInterface::class);
        $state->setEnabled(PageCache::TYPE_IDENTIFIER, $this->pageCacheWasEnabled);
        $state->setEnabled(RichSnippetsCache::TYPE_IDENTIFIER, $this->moduleCacheWasEnabled);
    }

    public function testSwitchingASnippetOnCleansThePagesOfItsKind(): void
    {
        $id = (int) $this->storeSnippet('rs-clean-product', 'Product', ['@type' => 'Product', 'name' => 'x'], [
            'kinds' => ['product'],
        ])->getId();
        [$product, $category] = $this->tagsOf(['product', 'category']);
        $this->cachePages([$product, $category]);

        $this->get(SnippetManagementInterface::class)->setActive($id, true);

        self::assertSame([$category], $this->cachedPages([$product, $category]));
    }

    public function testDeletingASwitchedOnSnippetCleansThePagesOfItsKind(): void
    {
        $id = (int) $this->storeSnippet('rs-clean-cms', 'Page', ['@type' => 'WebPage', 'name' => 'x'], [
            'kinds' => ['cms'],
            'active' => true,
        ])->getId();
        [$cms, $home] = $this->tagsOf(['cms', 'home']);
        $this->cachePages([$cms, $home]);

        $this->get(SnippetManagementInterface::class)->delete($id);

        self::assertSame([$home], $this->cachedPages([$cms, $home]));
    }

    public function testReplacingASwitchedOnSnippetByAnImportCleansThePagesOfTheOldAndTheNewKinds(): void
    {
        $id = (int) $this->storeSnippet('rs-clean-moved', 'Moved', ['@type' => 'WebPage', 'name' => 'x'], [
            'kinds' => ['cms'],
            'active' => true,
        ])->getId();
        $file = $this->get(SnippetExportInterface::class)->one($id)->getDocument();
        $snippets = $file['snippets'] ?? null;
        self::assertIsArray($snippets);
        self::assertIsArray($snippets[0] ?? null);
        $snippets[0]['pageKinds'] = ['category'];
        $file['snippets'] = $snippets;
        [$cms, $category, $product] = $this->tagsOf(['cms', 'category', 'product']);
        $this->cachePages([$cms, $category, $product]);

        $this->get(SnippetImportInterface::class)->commit(
            (string) json_encode($file, JSON_THROW_ON_ERROR),
            ['rs-clean-moved' => ImportChoice::Replace],
            self::KEY_PREFIX . 'replace'
        );

        self::assertSame([$product], $this->cachedPages([$cms, $category, $product]));
    }

    /**
     * The page cache tags of kinds of page in the default store view
     *
     * @param list<string> $kinds
     * @return list<string>
     */
    private function tagsOf(array $kinds): array
    {
        $storeId = (int) $this->get(StoreManagerInterface::class)->getDefaultStoreView()?->getId();

        return array_map(
            fn (string $kind): string => $this->get(PageCacheTagInterface::class)->forKind($kind, $storeId),
            $kinds
        );
    }

    /**
     * Fill the page cache with one entry per tag
     *
     * @param list<string> $tags
     * @return void
     */
    private function cachePages(array $tags): void
    {
        foreach ($tags as $tag) {
            $this->get(PageCache::class)->save('page of ' . $tag, 'rs_clean_' . $tag, [$tag]);
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
            fn (string $tag): bool => $this->get(PageCache::class)->load('rs_clean_' . $tag) !== false
        ));
    }

    /**
     * Delete the snippets and idempotency keys the tests stored
     *
     * @return void
     */
    private function deleteTestRows(): void
    {
        $connection = $this->get(ResourceConnection::class)->getConnection();
        $connection->delete(
            $connection->getTableName(SnippetResource::TABLE),
            [SnippetResource::CODE . ' LIKE ?' => self::KEY_PREFIX . '%']
        );
        $connection->delete(
            $connection->getTableName(IdempotencyEntry::TABLE),
            [IdempotencyEntry::KEY . ' LIKE ?' => self::KEY_PREFIX . '%']
        );
    }

    /**
     * A service from the container, of the type asked for
     *
     * @template T of object
     * @param class-string<T> $type
     * @return T
     */
    private function get(string $type): object
    {
        $service = Bootstrap::getObjectManager()->get($type);
        self::assertInstanceOf($type, $service);

        return $service;
    }
}
