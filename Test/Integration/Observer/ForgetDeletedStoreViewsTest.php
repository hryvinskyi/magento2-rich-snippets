<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Test\Integration\Observer;

use Hryvinskyi\RichSnippets\Api\Data\FlagTone;
use Hryvinskyi\RichSnippets\Api\Data\SnippetFlagInterface;
use Hryvinskyi\RichSnippets\Api\Data\SnippetInterface;
use Hryvinskyi\RichSnippets\Api\Data\SnippetRowInterface;
use Hryvinskyi\RichSnippets\Api\PageCacheTagInterface;
use Hryvinskyi\RichSnippets\Api\SnippetListInterface;
use Hryvinskyi\RichSnippets\Api\SnippetManagementInterface;
use Hryvinskyi\RichSnippets\Model\Cache\Type\RichSnippetsCache;
use Hryvinskyi\RichSnippets\Model\Placement\PlacementSentence;
use Hryvinskyi\RichSnippets\Model\ResourceModel\IdempotencyEntry;
use Hryvinskyi\RichSnippets\Model\ResourceModel\Snippet as SnippetResource;
use Hryvinskyi\RichSnippets\Model\Snippet\SnippetRootRepositoryInterface;
use Hryvinskyi\RichSnippets\Test\Integration\Fixture\StoresTestSnippets;
use Magento\Framework\App\Cache\StateInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Model\AbstractModel;
use Magento\PageCache\Model\Cache\Type as PageCache;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\Group;
use Magento\Store\Model\GroupFactory;
use Magento\Store\Model\ResourceModel\Group as GroupResource;
use Magento\Store\Model\ResourceModel\Store as StoreResource;
use Magento\Store\Model\ResourceModel\Website as WebsiteResource;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreFactory;
use Magento\Store\Model\Website;
use Magento\Store\Model\WebsiteFactory;
use Magento\Store\Model\StoreManagerInterface;
use Magento\TestFramework\Fixture\AppArea;
use Magento\TestFramework\Fixture\AppIsolation;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * Deleting a store view takes it out of every snippet: a snippet set for other store views too keeps those, a snippet
 * set for it alone is switched off and set for no store view — never for every store view — and says so in the list,
 * and the pages of the change are cleaned as for any other write to a snippet. Deleting a store group or a website
 * takes their store views out of the snippets the same way.
 *
 * The platform announces each deletion once it is committed, which never happens inside the transaction a
 * database-isolated test runs in, so this test commits for real and deletes what it stored afterwards: its snippets,
 * and the store view, store group and website it created when a test left them.
 */
#[AppArea('adminhtml'), DbIsolation(false), AppIsolation(true)]
class ForgetDeletedStoreViewsTest extends TestCase
{
    use StoresTestSnippets;

    private const PREFIX = 'rs-gone-';
    private const STORE_CODE = 'rs_gone_view';
    private const GROUP_CODE = 'rs_gone_group';
    private const WEBSITE_CODE = 'rs_gone_site';

    private const TEMPLATE = ['@context' => 'https://schema.org', '@type' => 'Product', 'name' => '{{product.name}}'];

    /**
     * @var bool
     */
    private bool $pageCacheWasEnabled = false;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        $state = $this->get(StateInterface::class);
        $this->pageCacheWasEnabled = $state->isEnabled(PageCache::TYPE_IDENTIFIER);
        $state->setEnabled(PageCache::TYPE_IDENTIFIER, true);
        $this->cleanUp();
    }

    /**
     * @return void
     */
    protected function tearDown(): void
    {
        $this->cleanUp();
        $this->get(PageCache::class)->clean();
        $this->get(RichSnippetsCache::class)->clean();
        $this->get(StateInterface::class)->setEnabled(PageCache::TYPE_IDENTIFIER, $this->pageCacheWasEnabled);
    }

    public function testEverySnippetLosesTheDeletedStoreViewAndOneSetForItAloneIsSwitchedOff(): void
    {
        $gone = $this->createStoreView();
        $kept = $this->defaultStoreId();
        $this->storeSnippet(self::PREFIX . 'alone', 'RS gone alone', self::TEMPLATE, [
            'kinds' => ['product'], 'stores' => [$gone], 'active' => true,
        ]);
        $this->storeSnippet(self::PREFIX . 'shared', 'Shared', self::TEMPLATE, [
            'kinds' => ['product'], 'stores' => [$kept, $gone], 'active' => true,
        ]);
        $this->storeSnippet(self::PREFIX . 'every', 'Every', self::TEMPLATE, [
            'kinds' => ['product'], 'stores' => [SnippetInterface::ALL_STORES], 'active' => true,
        ]);

        $this->deleteStoreView($gone);

        $alone = $this->stored('alone');
        self::assertSame([], $alone->getStoreIds());
        self::assertFalse($alone->isActive());
        $shared = $this->stored('shared');
        self::assertSame([$kept], $shared->getStoreIds());
        self::assertTrue($shared->isActive());
        $every = $this->stored('every');
        self::assertSame([SnippetInterface::ALL_STORES], $every->getStoreIds());
        self::assertTrue($every->isActive());
    }

    public function testTheListSaysWhyASnippetLeftWithNoStoreViewIsOutputNowhere(): void
    {
        $gone = $this->createStoreView();
        $this->storeSnippet(self::PREFIX . 'alone', 'RS gone alone', self::TEMPLATE, [
            'kinds' => ['product'], 'stores' => [$gone], 'active' => true,
        ]);

        $this->deleteStoreView($gone);

        $row = $this->row('alone');
        self::assertSame([], $row->getStoreNames());
        self::assertContains(
            [FlagTone::Warn->value, 'Every store view it was set for has been deleted, so it is output nowhere. '
                . 'Pick a store view to output it again.'],
            array_map(
                static fn (SnippetFlagInterface $flag): array => [$flag->getTone()->value, $flag->getText()],
                $row->getFlags()
            )
        );
        $sentence = $this->get(PlacementSentence::class)->describe(['product'], $this->stored('alone')
            ->getConditions(), [], [], $this->defaultStoreId());
        self::assertSame('Output nowhere: it is set for no store view.', $sentence['sentence']);
    }

    public function testASwitchedOnSnippetThatKeepsAStoreViewCleansThePagesOfItsKind(): void
    {
        $gone = $this->createStoreView();
        $kept = $this->defaultStoreId();
        $this->storeSnippet(self::PREFIX . 'shared', 'Shared', self::TEMPLATE, [
            'kinds' => ['product'], 'stores' => [$kept, $gone], 'active' => true,
        ]);
        $tag = $this->get(PageCacheTagInterface::class)->forKind('product', $kept);
        $this->get(PageCache::class)->save('a product page', 'rs_gone_page', [$tag]);

        $this->deleteStoreView($gone);

        self::assertFalse($this->get(PageCache::class)->load('rs_gone_page'));
    }

    public function testDeletingAStoreGroupTakesItsStoreViewsOutOfEverySnippet(): void
    {
        $default = $this->defaultStore();
        $group = $this->createGroup((int) $default->getWebsiteId());
        $gone = $this->createStoreView((int) $default->getWebsiteId(), $group);
        $kept = $this->defaultStoreId();
        $this->storeSnippet(self::PREFIX . 'alone', 'RS gone alone', self::TEMPLATE, [
            'kinds' => ['product'], 'stores' => [$gone], 'active' => true,
        ]);
        $this->storeSnippet(self::PREFIX . 'shared', 'Shared', self::TEMPLATE, [
            'kinds' => ['product'], 'stores' => [$kept, $gone], 'active' => true,
        ]);

        $this->deleteGroup($group);

        $alone = $this->stored('alone');
        self::assertSame([], $alone->getStoreIds());
        self::assertFalse($alone->isActive());
        $shared = $this->stored('shared');
        self::assertSame([$kept], $shared->getStoreIds());
        self::assertTrue($shared->isActive());
    }

    public function testDeletingAWebsiteTakesItsStoreViewsOutOfEverySnippet(): void
    {
        $website = $this->createWebsite();
        $gone = $this->createStoreView($website, $this->createGroup($website));
        $kept = $this->defaultStoreId();
        $this->storeSnippet(self::PREFIX . 'alone', 'RS gone alone', self::TEMPLATE, [
            'kinds' => ['product'], 'stores' => [$gone], 'active' => true,
        ]);
        $this->storeSnippet(self::PREFIX . 'shared', 'Shared', self::TEMPLATE, [
            'kinds' => ['product'], 'stores' => [$kept, $gone], 'active' => true,
        ]);

        $this->deleteWebsite($website);

        $alone = $this->stored('alone');
        self::assertSame([], $alone->getStoreIds());
        self::assertFalse($alone->isActive());
        $shared = $this->stored('shared');
        self::assertSame([$kept], $shared->getStoreIds());
        self::assertTrue($shared->isActive());
    }

    /**
     * The copy's code comes from its name, "RS gone alone (copy)", so the clean-up finds it by the prefix
     *
     * @return void
     */
    public function testACopyOfASnippetSetForNoStoreViewIsADraftSwitchedOffForEveryStoreView(): void
    {
        $gone = $this->createStoreView();
        $this->storeSnippet(self::PREFIX . 'alone', 'RS gone alone', self::TEMPLATE, [
            'kinds' => ['product'], 'stores' => [$gone], 'active' => true,
        ]);
        $this->deleteStoreView($gone);

        $copy = $this->get(SnippetManagementInterface::class)->duplicate(
            (int) $this->stored('alone')->getId(),
            self::PREFIX . 'copy-key'
        );

        self::assertStringStartsWith(self::PREFIX, $copy->getCode());
        self::assertFalse($copy->isActive());
        self::assertSame(['product'], $copy->getPageKinds());
        self::assertSame([SnippetInterface::ALL_STORES], $copy->getStoreIds());
        self::assertSame([], $this->stored('alone')->getStoreIds());
    }

    /**
     * Create a store view, committed: in the default website and store group unless others are given
     *
     * @param int|null $websiteId
     * @param int|null $groupId
     * @return int its ID
     */
    private function createStoreView(?int $websiteId = null, ?int $groupId = null): int
    {
        $store = $this->get(StoreFactory::class)->create();
        self::assertInstanceOf(Store::class, $store);
        $default = $this->defaultStore();
        $store->setData([
            'code' => self::STORE_CODE,
            'name' => 'Gone RS',
            'website_id' => $websiteId ?? (int) $default->getWebsiteId(),
            'group_id' => $groupId ?? (int) $default->getStoreGroupId(),
            'is_active' => 1,
            'sort_order' => 90,
        ]);
        $this->get(StoreResource::class)->save($store);
        $this->get(StoreManagerInterface::class)->reinitStores();

        return (int) $store->getId();
    }

    /**
     * Create a store group in a website, under the default store view's root category, committed
     *
     * @param int $websiteId
     * @return int its ID
     */
    private function createGroup(int $websiteId): int
    {
        $group = $this->get(GroupFactory::class)->create();
        self::assertInstanceOf(Group::class, $group);
        $defaultGroup = $this->get(StoreManagerInterface::class)
            ->getGroup((string) $this->defaultStore()->getStoreGroupId());
        $group->setData([
            'code' => self::GROUP_CODE,
            'name' => 'Gone RS group',
            'website_id' => $websiteId,
            'root_category_id' => (int) $defaultGroup->getRootCategoryId(),
        ]);
        $this->get(GroupResource::class)->save($group);
        $this->get(StoreManagerInterface::class)->reinitStores();

        return $this->idOf($group);
    }

    /**
     * Create a website, committed
     *
     * @return int its ID
     */
    private function createWebsite(): int
    {
        $website = $this->get(WebsiteFactory::class)->create();
        self::assertInstanceOf(Website::class, $website);
        $website->setData(['code' => self::WEBSITE_CODE, 'name' => 'Gone RS site', 'sort_order' => 90]);
        $this->get(WebsiteResource::class)->save($website);
        $this->get(StoreManagerInterface::class)->reinitStores();

        return $this->idOf($website);
    }

    /**
     * Delete a store view the way the admin does, committed
     *
     * @param int $storeId
     * @return void
     */
    private function deleteStoreView(int $storeId): void
    {
        $store = $this->get(StoreFactory::class)->create();
        self::assertInstanceOf(Store::class, $store);
        $this->get(StoreResource::class)->load($store, $storeId);
        self::assertSame($storeId, (int) $store->getId());
        $this->get(StoreResource::class)->delete($store);
    }

    /**
     * Delete a store group the way the admin does, committed
     *
     * @param int $groupId
     * @return void
     */
    private function deleteGroup(int $groupId): void
    {
        $group = $this->get(GroupFactory::class)->create();
        self::assertInstanceOf(Group::class, $group);
        $this->get(GroupResource::class)->load($group, $groupId);
        self::assertSame($groupId, $this->idOf($group));
        $this->get(GroupResource::class)->delete($group);
    }

    /**
     * Delete a website the way the admin does, committed
     *
     * @param int $websiteId
     * @return void
     */
    private function deleteWebsite(int $websiteId): void
    {
        $website = $this->get(WebsiteFactory::class)->create();
        self::assertInstanceOf(Website::class, $website);
        $this->get(WebsiteResource::class)->load($website, $websiteId);
        self::assertSame($websiteId, $this->idOf($website));
        $this->get(WebsiteResource::class)->delete($website);
    }

    /**
     * A test snippet as stored now
     *
     * @param string $name its code without the prefix
     * @return SnippetInterface
     */
    private function stored(string $name): SnippetInterface
    {
        return $this->get(SnippetRootRepositoryInterface::class)->getByCode(self::PREFIX . $name);
    }

    /**
     * A test snippet's row in the list of every store view
     *
     * @param string $name its code without the prefix
     * @return SnippetRowInterface
     */
    private function row(string $name): SnippetRowInterface
    {
        foreach ($this->get(SnippetListInterface::class)->list(null)->getRows() as $row) {
            if ($row->getSnippet()->getCode() === self::PREFIX . $name) {
                return $row;
            }
        }
        self::fail(self::PREFIX . $name . ' is not listed.');
    }

    /**
     * @return int
     */
    private function defaultStoreId(): int
    {
        return (int) $this->defaultStore()->getId();
    }

    /**
     * @return StoreInterface
     */
    private function defaultStore(): StoreInterface
    {
        $default = $this->get(StoreManagerInterface::class)->getDefaultStoreView();
        self::assertNotNull($default);

        return $default;
    }

    /**
     * Delete the test snippets, their idempotency keys and the test store view, store group and website
     *
     * @return void
     */
    private function cleanUp(): void
    {
        $connection = $this->get(ResourceConnection::class)->getConnection();
        $connection->delete(
            $connection->getTableName(SnippetResource::TABLE),
            [SnippetResource::CODE . ' LIKE ?' => self::PREFIX . '%']
        );
        $connection->delete(
            $connection->getTableName(IdempotencyEntry::TABLE),
            [IdempotencyEntry::KEY . ' LIKE ?' => self::PREFIX . '%']
        );
        $store = $this->get(StoreFactory::class)->create();
        self::assertInstanceOf(Store::class, $store);
        $this->get(StoreResource::class)->load($store, self::STORE_CODE, 'code');
        if ((int) $store->getId() > 0) {
            $this->get(StoreResource::class)->delete($store);
        }
        $group = $this->get(GroupFactory::class)->create();
        self::assertInstanceOf(Group::class, $group);
        $this->get(GroupResource::class)->load($group, self::GROUP_CODE, 'code');
        if ($this->idOf($group) > 0) {
            $this->get(GroupResource::class)->delete($group);
        }
        $website = $this->get(WebsiteFactory::class)->create();
        self::assertInstanceOf(Website::class, $website);
        $this->get(WebsiteResource::class)->load($website, self::WEBSITE_CODE, 'code');
        if ($this->idOf($website) > 0) {
            $this->get(WebsiteResource::class)->delete($website);
        }
        $this->get(StoreManagerInterface::class)->reinitStores();
    }

    /**
     * The ID of a store model; 0 while it is not stored
     *
     * @param AbstractModel $model
     * @return int
     */
    private function idOf(AbstractModel $model): int
    {
        $id = $model->getId();

        return is_numeric($id) ? (int) $id : 0;
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
