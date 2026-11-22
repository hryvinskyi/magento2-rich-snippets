<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Test\Integration\Model\Page;

use Hryvinskyi\RichSnippets\Api\Data\PageMatchInterface;
use Hryvinskyi\RichSnippets\Api\PageSearchInterface;
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
 * The page search finds a page by the exact text typed, whatever characters it holds
 *
 * A question mark is the placeholder of a bound SQL value, so a search text holding one must reach the database as
 * typed rather than be read as a place to put a value.
 */
#[
    DbIsolation(true),
    DataFixture(ProductFixture::class, ['sku' => 'rs-search-what', 'name' => 'What? RS bag'], as: 'product'),
    DataFixture(PageFixture::class, ['identifier' => 'rs-search-what', 'title' => 'What? RS page'], as: 'page'),
]
class PageSearchTest extends TestCase
{
    public function testAProductAndACmsPageNamedWithAQuestionMarkAreFoundByThatName(): void
    {
        $found = $this->search('What?', ['product', 'cms']);

        self::assertSame(
            [
                'product:' . $this->fixtureId('product') => 'What? RS bag',
                'cms:' . $this->fixtureId('page') => 'What? RS page',
            ],
            $found
        );
    }

    public function testAQuestionMarkStandsForItselfAndForNoOtherCharacter(): void
    {
        self::assertSame([], $this->search('What! RS', ['product', 'cms']));
    }

    /**
     * The pages found for a text, as `kind:id` => label
     *
     * @param string $query
     * @param list<string> $kinds
     * @return array<string, string>
     */
    private function search(string $query, array $kinds): array
    {
        $search = Bootstrap::getObjectManager()->get(PageSearchInterface::class);
        self::assertInstanceOf(PageSearchInterface::class, $search);
        $stores = Bootstrap::getObjectManager()->get(StoreManagerInterface::class);
        self::assertInstanceOf(StoreManagerInterface::class, $stores);
        $storeId = (int) $stores->getDefaultStoreView()?->getId();

        $found = [];
        foreach ($search->search($query, $storeId, $kinds, 10) as $match) {
            self::assertInstanceOf(PageMatchInterface::class, $match);
            $found[$match->getPage()->getKind() . ':' . $match->getPage()->getEntityId()] = $match->getLabel();
        }

        return $found;
    }

    /**
     * A fixture's ID
     *
     * @param string $name
     * @return int
     */
    private function fixtureId(string $name): int
    {
        $fixture = DataFixtureStorageManager::getStorage()->get($name);
        self::assertInstanceOf(AbstractModel::class, $fixture);
        $id = $fixture->getId();
        self::assertIsNumeric($id);

        return (int) $id;
    }
}
