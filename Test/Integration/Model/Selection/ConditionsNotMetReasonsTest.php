<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Test\Integration\Model\Selection;

use Hryvinskyi\RichSnippets\Api\Data\PageEntityInterface;
use Hryvinskyi\RichSnippets\Api\PageEntityFactoryInterface;
use Hryvinskyi\RichSnippets\Api\SnippetSelectorInterface;
use Hryvinskyi\RichSnippets\Model\Admin\Test\NotOutputReasons;
use Hryvinskyi\RichSnippets\Model\Cache\Type\RichSnippetsCache;
use Hryvinskyi\RichSnippets\Test\Integration\Fixture\StoresTestSnippets;
use Magento\Catalog\Test\Fixture\Product as ProductFixture;
use Magento\Cms\Test\Fixture\Page as PageFixture;
use Magento\Framework\Model\AbstractModel;
use Magento\Store\Model\StoreManagerInterface;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Fixture\DataFixtureStorageManager;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * Why a page does not get a snippet whose conditions it fails, in the words the page test and the editor show
 *
 * One condition the page fails names what it asks and, when the page has a value of its own, that value; any
 * other tree is quoted as the Pages section words it.
 */
#[
    DbIsolation(true),
    DataFixture(ProductFixture::class, as: 'simple'),
    DataFixture(PageFixture::class, ['identifier' => 'rs-test-faq', 'title' => 'FAQ RS'], as: 'faq'),
    DataFixture(PageFixture::class, ['identifier' => 'rs-test-terms', 'title' => 'Terms RS'], as: 'terms'),
]
class ConditionsNotMetReasonsTest extends TestCase
{
    use StoresTestSnippets;

    private const TEMPLATE = ['@type' => 'Thing', 'name' => '{{ store.name }}'];

    /**
     * @return void
     */
    protected function setUp(): void
    {
        $this->get(RichSnippetsCache::class)->clean();
    }

    /**
     * @return void
     */
    protected function tearDown(): void
    {
        $this->get(RichSnippetsCache::class)->clean();
    }

    public function testOneConditionWithAValueOfThePageNamesBoth(): void
    {
        $this->storeSnippet('rs-test-variants', 'Variants', self::TEMPLATE, [
            'kinds' => ['product'],
            'conditions' => [['product_type', '==', 'configurable']],
            'active' => true,
        ]);

        self::assertSame(
            ['rs-test-variants' => 'Set for product type Configurable Product; this one is Simple Product.'],
            $this->reasons($this->page('product', 'simple'))
        );
    }

    public function testOneConditionOnThePageItselfNamesWhatItAsksOnly(): void
    {
        $this->storeSnippet('rs-test-faq', 'FAQ', self::TEMPLATE, [
            'kinds' => ['cms'],
            'conditions' => [['cms_page', '()', 'rs-test-faq']],
            'active' => true,
        ]);

        self::assertSame(
            ['rs-test-faq' => 'Set for the CMS page FAQ RS only.'],
            $this->reasons($this->page('cms', 'terms'))
        );
    }

    public function testAnyOtherTreeIsQuotedAsThePagesSectionWordsIt(): void
    {
        $this->storeSnippet('rs-test-either', 'Either', self::TEMPLATE, [
            'kinds' => ['product'],
            'conditions' => [['product_type', '==', 'virtual'], ['product_type', '==', 'configurable']],
            'match' => 'any',
            'active' => true,
        ]);

        self::assertSame(
            ['rs-test-either' => 'The conditions in Pages ask for '
                . '“Product type is Virtual Product or Product type is Configurable Product”.'],
            $this->reasons($this->page('product', 'simple'))
        );
    }

    /**
     * The test's snippets a page does not get for their conditions, with the reason's text, by code
     *
     * @param PageEntityInterface $page
     * @return array<string, string>
     */
    private function reasons(PageEntityInterface $page): array
    {
        $exclusions = $this->get(SnippetSelectorInterface::class)->explain($page);
        $texts = [];
        foreach ($this->get(NotOutputReasons::class)->rows($exclusions, [], $page->getStoreId()) as $row) {
            if ($row['reason'] === 'conditions_not_met' && str_starts_with($row['code'], 'rs-test-')) {
                $texts[$row['code']] = $row['text'];
            }
        }

        return $texts;
    }

    /**
     * A page of a kind in the default store view, showing a fixture entity
     *
     * @param string $kind
     * @param string $fixture
     * @return PageEntityInterface
     */
    private function page(string $kind, string $fixture): PageEntityInterface
    {
        $entity = DataFixtureStorageManager::getStorage()->get($fixture);
        self::assertInstanceOf(AbstractModel::class, $entity);
        $id = $entity->getId();
        self::assertIsNumeric($id);
        $storeId = (int) $this->get(StoreManagerInterface::class)->getDefaultStoreView()?->getId();

        return $this->get(PageEntityFactoryInterface::class)->create($kind, (int) $id, $storeId);
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
