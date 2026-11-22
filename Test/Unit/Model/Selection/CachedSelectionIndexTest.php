<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Test\Unit\Model\Selection;

use Hryvinskyi\RichSnippets\Api\Data\SnippetInterface;
use Hryvinskyi\RichSnippets\Model\ResourceModel\Snippet as SnippetResource;
use Hryvinskyi\RichSnippets\Model\Selection\CachedSelectionIndex;
use Hryvinskyi\RichSnippets\Model\Snippet;
use Hryvinskyi\RichSnippets\Model\Snippet\Storage\SnippetRowMapper;
use Hryvinskyi\RichSnippets\Test\Unit\Fake\InMemoryCache;
use Hryvinskyi\RichSnippets\Test\Unit\Fake\PlacementFixture;
use Magento\Framework\Cache\FrontendInterface;
use Magento\Framework\Serialize\Serializer\Json;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * The selection index never keeps serving rows read before a write that committed and forgot the store view
 *
 * The stored rows, the moment a write commits and the moment it forgets the store view are put between the steps
 * of a build by hand, so each interleaving runs the same way every time. Within one request a store view's stored
 * snippets are read once, whether or not the cache type is enabled.
 */
class CachedSelectionIndexTest extends TestCase
{
    private const STORE = 1;

    /**
     * @var list<array<string, mixed>> the rows the database holds now
     */
    private array $stored = [];

    /**
     * @var (\Closure(): void)|null runs once, after the next build has read its rows
     */
    private ?\Closure $afterRead = null;

    /**
     * @var int
     */
    private int $reads = 0;

    /**
     * @var InMemoryCache
     */
    private InMemoryCache $cache;

    /**
     * @var CachedSelectionIndex
     */
    private CachedSelectionIndex $index;

    /**
     * @var SnippetResource&MockObject
     */
    private SnippetResource $resource;

    /**
     * @var SnippetRowMapper&MockObject
     */
    private SnippetRowMapper $mapper;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        $this->cache = new InMemoryCache();
        $resource = $this->createMock(SnippetResource::class);
        $resource->method('fetchMatching')->willReturnCallback(function (): array {
            $this->reads++;
            $rows = $this->stored;
            $afterRead = $this->afterRead;
            $this->afterRead = null;
            if ($afterRead !== null) {
                $afterRead();
            }

            return $rows;
        });
        $mapper = $this->createMock(SnippetRowMapper::class);
        $mapper->method('toSnippet')->willReturnCallback(
            static fn (array $row): SnippetInterface => new Snippet(
                is_int($row['id']) ? $row['id'] : 0,
                is_string($row['code']) ? $row['code'] : '',
                is_string($row['code']) ? $row['code'] : '',
                '{"@type": "Thing"}',
                'Thing',
                false,
                true,
                ['product'],
                PlacementFixture::none(),
                [0],
                null,
                is_int($row['id']) ? $row['id'] : 0,
                null,
                ''
            )
        );
        $this->resource = $resource;
        $this->mapper = $mapper;
        $this->index = $this->indexOver($this->cache);
    }

    public function testAStoreViewIsBuiltOnceAndThenReadFromTheCache(): void
    {
        $this->stored = [$this->row(1, 'product')];

        self::assertSame(['product'], $this->codes());
        self::assertSame(['product'], $this->codes());
        self::assertSame(1, $this->reads);
    }

    public function testAWriteThatCommitsAfterTheRowsWereReadKeepsTheStaleBuildFromBeingKept(): void
    {
        $this->stored = [$this->row(1, 'product')];
        $this->afterRead = function (): void {
            $this->stored = [$this->row(1, 'product'), $this->row(2, 'variants')];
            $this->index->invalidate([self::STORE]);
        };

        self::assertSame(['product'], $this->codes(), 'The page that read before the commit sees the old rows.');
        self::assertSame(['product', 'variants'], $this->codes(), 'The next page builds again from the new rows.');
        self::assertSame(['product', 'variants'], $this->codes());
        self::assertSame(2, $this->reads);
    }

    public function testAWriteThatForgetsTheStoreViewJustBeforeTheBuildIsKeptLeavesAnEntryNobodyReads(): void
    {
        $this->stored = [$this->row(1, 'product')];
        $this->cache->beforeSaving('rich_snippets_selection_' . self::STORE, function (): void {
            $this->stored = [];
            $this->index->invalidate([self::STORE]);
        });

        self::assertSame(['product'], $this->codes());
        self::assertSame([], $this->codes(), 'The entry kept under the old generation is not read.');
        self::assertSame(2, $this->reads);
    }

    public function testAWriteForgetsTheStoreViewSoTheNextPageReadsTheNewRows(): void
    {
        $this->stored = [$this->row(1, 'product')];
        self::assertSame(['product'], $this->codes());

        $this->stored = [$this->row(1, 'product'), $this->row(2, 'variants')];
        $this->index->invalidate([self::STORE]);

        self::assertSame(['product', 'variants'], $this->codes());
        self::assertSame(2, $this->reads);
    }

    public function testAFlushedCacheIsBuiltAgain(): void
    {
        $this->stored = [$this->row(1, 'product')];
        self::assertSame(['product'], $this->codes());

        $this->cache->clean();
        $this->stored = [$this->row(2, 'variants')];

        self::assertSame(['variants'], $this->codes());
        self::assertSame(['variants'], $this->codes());
        self::assertSame(2, $this->reads);
    }

    public function testForgettingAnotherStoreViewLeavesThisOneCached(): void
    {
        $this->stored = [$this->row(1, 'product')];
        self::assertSame(['product'], $this->codes());

        $this->index->invalidate([2]);

        self::assertSame(['product'], $this->codes());
        self::assertSame(1, $this->reads);
    }

    public function testWithinOneRequestAStoreViewIsReadOnceThoughTheCacheTypeIsDisabled(): void
    {
        $this->index = $this->indexOver($this->disabledCache());
        $this->stored = [$this->row(1, 'product')];

        self::assertSame(['product'], $this->codes());
        self::assertSame(['product'], $this->codes());
        self::assertSame(['product'], $this->codes());
        self::assertSame(1, $this->reads);
    }

    public function testWithTheCacheTypeDisabledAWriteInThisRequestIsSeenAtOnce(): void
    {
        $this->index = $this->indexOver($this->disabledCache());
        $this->stored = [$this->row(1, 'product')];
        self::assertSame(['product'], $this->codes());

        $this->stored = [$this->row(1, 'product'), $this->row(2, 'variants')];
        $this->index->invalidate([self::STORE]);

        self::assertSame(['product', 'variants'], $this->codes());
        self::assertSame(2, $this->reads);
    }

    public function testWithTheCacheTypeDisabledTheNextRequestReadsTheStoredSnippetsAgain(): void
    {
        $this->index = $this->indexOver($this->disabledCache());
        $this->stored = [$this->row(1, 'product')];
        self::assertSame(['product'], $this->codes());

        $this->stored = [$this->row(2, 'variants')];
        $this->index->_resetState();

        self::assertSame(['variants'], $this->codes());
        self::assertSame(2, $this->reads);
    }

    public function testAWriteInAnotherProcessIsSeenByTheIndexThisRequestRemembers(): void
    {
        $this->stored = [$this->row(1, 'product')];
        self::assertSame(['product'], $this->codes());

        $this->stored = [$this->row(2, 'variants')];
        $this->indexOver($this->cache)->invalidate([self::STORE]);

        self::assertSame(['variants'], $this->codes());
        self::assertSame(['variants'], $this->codes());
        self::assertSame(2, $this->reads);
    }

    public function testAStoreViewBuiltByAnotherProcessIsReadFromTheCacheNotTheDatabase(): void
    {
        $this->stored = [$this->row(1, 'product')];
        self::assertCount(1, $this->indexOver($this->cache)->snippetsFor('product', self::STORE));

        self::assertSame(['product'], $this->codes());
        self::assertSame(1, $this->reads);
    }

    /**
     * An index over a cache, reading the rows and mapping them as the test's database does
     *
     * @param FrontendInterface $cache
     * @return CachedSelectionIndex
     */
    private function indexOver(FrontendInterface $cache): CachedSelectionIndex
    {
        return new CachedSelectionIndex($cache, $this->resource, $this->mapper, new Json());
    }

    /**
     * A cache as a disabled cache type is: it keeps nothing and finds nothing
     *
     * @return FrontendInterface
     */
    private function disabledCache(): FrontendInterface
    {
        $cache = $this->createMock(FrontendInterface::class);
        $cache->method('load')->willReturn(false);
        $cache->method('save')->willReturn(true);
        $cache->method('clean')->willReturn(true);

        return $cache;
    }

    /**
     * The codes of the product page's snippets in the store view, read afresh from the index
     *
     * @return list<string>
     * @phpstan-impure
     */
    private function codes(): array
    {
        return array_map(
            static fn (SnippetInterface $snippet): string => $snippet->getCode(),
            $this->index->snippetsFor('product', self::STORE)
        );
    }

    /**
     * A stored row as the mapper above reads it
     *
     * @param int $id
     * @param string $code
     * @return array<string, mixed>
     */
    private function row(int $id, string $code): array
    {
        return ['id' => $id, 'code' => $code];
    }
}
