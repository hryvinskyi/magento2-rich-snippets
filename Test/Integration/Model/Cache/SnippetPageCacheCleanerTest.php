<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Test\Integration\Model\Cache;

use Hryvinskyi\RichSnippets\Api\ConditionTreeParserInterface;
use Hryvinskyi\RichSnippets\Api\Data\ConditionTreeInterface;
use Hryvinskyi\RichSnippets\Api\Data\SnippetInterface;
use Hryvinskyi\RichSnippets\Api\PageCacheTagInterface;
use Hryvinskyi\RichSnippets\Api\PlacementVocabularyInterface;
use Hryvinskyi\RichSnippets\Api\SnippetRepositoryInterface;
use Hryvinskyi\RichSnippets\Model\Cache\Type\RichSnippetsCache;
use Hryvinskyi\RichSnippets\Model\ResourceModel\Snippet as SnippetResource;
use Hryvinskyi\RichSnippets\Model\Selection\SelectionIndexInterface;
use Hryvinskyi\RichSnippets\Model\Snippet\SnippetFactoryInterface;
use Hryvinskyi\RichSnippets\Model\Snippet\SnippetRootInterface;
use Hryvinskyi\RichSnippets\Model\Snippet\SnippetRootRepositoryInterface;
use Hryvinskyi\RichSnippets\Model\Snippet\TemplateTypeInterface;
use Magento\Framework\App\Cache\StateInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\ObjectManagerInterface;
use Magento\PageCache\Model\Cache\Type as PageCache;
use Magento\Store\Model\StoreManagerInterface;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * A snippet change cleans the built-in page cache of exactly the pages it reaches, and only once it is committed
 *
 * The cleaning runs as a commit callback, which never fires inside the transaction a database-isolated test runs
 * in, so these tests commit for real and delete what they stored afterwards. Each test fills the page cache with one
 * entry per tag it cares about and reads which entries are left.
 */
#[DbIsolation(false)]
class SnippetPageCacheCleanerTest extends TestCase
{
    private const PRODUCT_TEMPLATE = '{"@type": "Product", "hasMerchantReturnPolicy": {"@include": "rs-test-policy"}}';
    private const POLICY_TEMPLATE = '{"@type": "MerchantReturnPolicy", "merchantReturnDays": 30}';

    /**
     * @var ObjectManagerInterface
     */
    private ObjectManagerInterface $objectManager;

    /**
     * @var bool
     */
    private bool $pageCacheWasEnabled = false;

    /**
     * @var bool
     */
    private bool $moduleCacheWasEnabled = false;

    /**
     * @var int
     */
    private int $storeId = 0;

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
        $this->moduleCacheWasEnabled = $state->isEnabled(RichSnippetsCache::TYPE_IDENTIFIER);
        $state->setEnabled(RichSnippetsCache::TYPE_IDENTIFIER, true);
        $this->storeId = (int) $this->get(StoreManagerInterface::class)->getDefaultStoreView()?->getId();
        $this->deleteTestSnippets();
        $this->get(RichSnippetsCache::class)->clean();
    }

    /**
     * Delete the snippets the test stored and put the page cache back as it was
     *
     * @return void
     */
    protected function tearDown(): void
    {
        $this->deleteTestSnippets();
        $this->pageCache()->clean();
        $this->get(RichSnippetsCache::class)->clean();
        $state = $this->get(StateInterface::class);
        $state->setEnabled(PageCache::TYPE_IDENTIFIER, $this->pageCacheWasEnabled);
        $state->setEnabled(RichSnippetsCache::TYPE_IDENTIFIER, $this->moduleCacheWasEnabled);
    }

    public function testSavingASwitchedOnSnippetCleansItsPagesOnlyAfterTheCommit(): void
    {
        $productPages = $this->tags()->forKind('product', $this->storeId);
        $categoryPages = $this->tags()->forKind('category', $this->storeId);
        $this->cachePages([$productPages, $categoryPages]);
        $snippet = $this->draft('rs-test-product', self::PRODUCT_TEMPLATE);
        $snippet->place(['product'], $this->noConditions(), [0], $this->get(PlacementVocabularyInterface::class));
        $snippet->switchOn();
        $resource = $this->get(SnippetResource::class);

        $resource->beginTransaction();
        try {
            $this->repository()->save($snippet);
            self::assertSame([$productPages, $categoryPages], $this->cachedPages([$productPages, $categoryPages]));
            $resource->commit();
        } catch (\Throwable $e) {
            $resource->rollBack();
            throw $e;
        }

        self::assertSame([$categoryPages], $this->cachedPages([$productPages, $categoryPages]));
    }

    public function testMovingASnippetToAnotherKindCleansTheOldAndTheNewPages(): void
    {
        $snippet = $this->draft('rs-test-product', self::PRODUCT_TEMPLATE);
        $snippet->place(['product'], $this->noConditions(), [0], $this->get(PlacementVocabularyInterface::class));
        $snippet->switchOn();
        $stored = $this->rootRepository()->save($snippet);
        $productPages = $this->tags()->forKind('product', $this->storeId);
        $categoryPages = $this->tags()->forKind('category', $this->storeId);
        $cmsPages = $this->tags()->forKind('cms', $this->storeId);
        $this->cachePages([$productPages, $categoryPages, $cmsPages]);

        $stored->place(['category'], $this->noConditions(), [0], $this->get(PlacementVocabularyInterface::class));
        $this->repository()->save($stored);

        self::assertSame([$cmsPages], $this->cachedPages([$productPages, $categoryPages, $cmsPages]));
    }

    public function testAddingASwitchedOffSnippetCleansNothing(): void
    {
        $productPages = $this->tags()->forKind('product', $this->storeId);
        $this->cachePages([$productPages]);
        $snippet = $this->draft('rs-test-product', self::PRODUCT_TEMPLATE);
        $snippet->place(['product'], $this->noConditions(), [0], $this->get(PlacementVocabularyInterface::class));

        $this->repository()->save($snippet);

        self::assertSame([$productPages], $this->cachedPages([$productPages]));
    }

    public function testChangingABuildingBlockCleansThePagesOfTheSnippetsThatIncludeIt(): void
    {
        $block = $this->rootRepository()->save($this->draft('rs-test-policy', self::POLICY_TEMPLATE, true));
        $product = $this->draft('rs-test-product', self::PRODUCT_TEMPLATE);
        $product->place(['product'], $this->noConditions(), [0], $this->get(PlacementVocabularyInterface::class));
        $product->switchOn();
        $this->repository()->save($product);
        $productPages = $this->tags()->forKind('product', $this->storeId);
        $homePages = $this->tags()->forKind('home', $this->storeId);
        $this->cachePages([$productPages, $homePages]);

        $block->rename('Return policy, 60 days');
        $this->repository()->save($block);

        self::assertSame([$homePages], $this->cachedPages([$productPages, $homePages]));
    }

    public function testDeletingASwitchedOnSnippetCleansItsPages(): void
    {
        $snippet = $this->draft('rs-test-product', self::PRODUCT_TEMPLATE);
        $snippet->place(['cms'], $this->noConditions(), [0], $this->get(PlacementVocabularyInterface::class));
        $snippet->switchOn();
        $stored = $this->repository()->save($snippet);
        $cmsPages = $this->tags()->forKind('cms', $this->storeId);
        $this->cachePages([$cmsPages]);

        $this->repository()->delete($stored);

        self::assertSame([], $this->cachedPages([$cmsPages]));
    }

    public function testTheSelectionOfTheChangedStoreViewsIsBuiltAgainAfterTheCommit(): void
    {
        $storeWithoutChange = $this->storeId + 1000;
        self::assertNotContains('rs-test-product', $this->selectedCodes($this->storeId));
        self::assertNotContains('rs-test-product', $this->selectedCodes($storeWithoutChange));
        $snippet = $this->draft('rs-test-product', self::PRODUCT_TEMPLATE);
        $snippet->place(['product'], $this->noConditions(), [0], $this->get(PlacementVocabularyInterface::class));
        $snippet->switchOn();

        $this->repository()->save($snippet);

        self::assertContains('rs-test-product', $this->selectedCodes($this->storeId));
        self::assertNotContains(
            'rs-test-product',
            $this->selectedCodes($storeWithoutChange),
            'A store view the change does not reach keeps the selection it had.'
        );
    }

    public function testARolledBackSaveCleansNothing(): void
    {
        $productPages = $this->tags()->forKind('product', $this->storeId);
        $this->cachePages([$productPages]);
        $snippet = $this->draft('rs-test-product', self::PRODUCT_TEMPLATE);
        $snippet->place(['product'], $this->noConditions(), [0], $this->get(PlacementVocabularyInterface::class));
        $snippet->switchOn();
        $resource = $this->get(SnippetResource::class);

        $resource->beginTransaction();
        $this->repository()->save($snippet);
        $resource->rollBack();

        self::assertSame([$productPages], $this->cachedPages([$productPages]));
    }

    /**
     * A new snippet with a template
     *
     * @param string $code
     * @param string $template
     * @param bool $isBlock
     * @return SnippetRootInterface
     */
    private function draft(string $code, string $template, bool $isBlock = false): SnippetRootInterface
    {
        $snippet = $this->get(SnippetFactoryInterface::class)->draft($code, 'Test ' . $code, $isBlock);
        $snippet->changeTemplate($template, $this->get(TemplateTypeInterface::class));

        return $snippet;
    }

    /**
     * The codes of the product page snippets the selection index holds for a store view
     *
     * @param int $storeId
     * @return list<string>
     */
    private function selectedCodes(int $storeId): array
    {
        return array_map(
            static fn (SnippetInterface $snippet): string => $snippet->getCode(),
            $this->get(SelectionIndexInterface::class)->snippetsFor('product', $storeId)
        );
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
            $this->pageCache()->save('page of ' . $tag, 'rs_test_' . $tag, [$tag]);
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
            fn (string $tag): bool => $this->pageCache()->load('rs_test_' . $tag) !== false
        ));
    }

    /**
     * Delete every snippet whose code the tests use
     *
     * @return void
     */
    private function deleteTestSnippets(): void
    {
        $connection = $this->get(ResourceConnection::class)->getConnection();
        $connection->delete(
            $connection->getTableName(SnippetResource::TABLE),
            [SnippetResource::CODE . ' LIKE ?' => 'rs-test-%']
        );
    }

    /**
     * @return PageCache
     */
    private function pageCache(): PageCache
    {
        return $this->get(PageCache::class);
    }

    /**
     * @return PageCacheTagInterface
     */
    private function tags(): PageCacheTagInterface
    {
        return $this->get(PageCacheTagInterface::class);
    }

    /**
     * @return SnippetRepositoryInterface
     */
    private function repository(): SnippetRepositoryInterface
    {
        return $this->get(SnippetRepositoryInterface::class);
    }

    /**
     * @return SnippetRootRepositoryInterface
     */
    private function rootRepository(): SnippetRootRepositoryInterface
    {
        return $this->get(SnippetRootRepositoryInterface::class);
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
        $service = $this->objectManager->get($type);
        self::assertInstanceOf($type, $service);

        return $service;
    }

    /**
     * No conditions
     *
     * @return ConditionTreeInterface
     */
    private function noConditions(): ConditionTreeInterface
    {
        return $this->get(ConditionTreeParserInterface::class)->parse('');
    }
}
