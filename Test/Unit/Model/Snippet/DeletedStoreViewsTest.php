<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Test\Unit\Model\Snippet;

use Hryvinskyi\RichSnippets\Api\Data\SnippetInterface;
use Hryvinskyi\RichSnippets\Model\Snippet;
use Hryvinskyi\RichSnippets\Model\Snippet\DeletedStoreViews;
use Hryvinskyi\RichSnippets\Model\Snippet\SnippetRootInterface;
use Hryvinskyi\RichSnippets\Model\Snippet\SnippetRootRepositoryInterface;
use Hryvinskyi\RichSnippets\Test\Unit\Fake\TreeFactoryFake;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;

/**
 * Every snippet leaves the store views the shop no longer has, whichever deletion removed them, each snippet on its
 * own: one whose save fails is logged with its code and the others still leave them
 */
class DeletedStoreViewsTest extends TestCase
{
    public function testEverySnippetLeavesTheStoreViewsTheShopNoLongerHas(): void
    {
        $repository = $this->repository([
            'kept' => [1, 2],
            'some-gone' => [1, 5, 6],
            'all-gone' => [5, 6],
            'every' => [0],
        ], []);
        $storeManager = $this->storeManager([1, 2]);

        (new DeletedStoreViews($repository, $storeManager, $this->logger()))->forget();

        self::assertSame(['some-gone' => [1], 'all-gone' => []], $repository->saved);
    }

    public function testTheStoreViewsAreReadAfresh(): void
    {
        $storeManager = $this->storeManager([1]);
        $storeManager->expects(self::once())->method('reinitStores');

        (new DeletedStoreViews($this->repository([], []), $storeManager, $this->logger()))->forget();
    }

    public function testASnippetWhoseSaveFailsIsLoggedAndTheLaterOnesStillLeaveTheStoreView(): void
    {
        $repository = $this->repository(['first' => [1, 5], 'failing' => [5], 'last' => [5, 2]], ['failing']);
        $logger = $this->logger();

        (new DeletedStoreViews($repository, $this->storeManager([1, 2]), $logger))->forget();

        self::assertSame(['first' => [1], 'last' => [2]], $repository->saved);
        self::assertCount(1, $logger->records);
        self::assertSame('error', $logger->records[0]['level']);
        self::assertStringContainsString('"failing"', $logger->records[0]['message']);
        self::assertSame('failing', $logger->records[0]['context']['snippet'] ?? null);
        self::assertInstanceOf(\RuntimeException::class, $logger->records[0]['context']['exception'] ?? null);
    }

    /**
     * A store manager whose shop has the store views given, besides the admin's
     *
     * @param list<int> $storeIds
     * @return StoreManagerInterface&MockObject
     */
    private function storeManager(array $storeIds): StoreManagerInterface
    {
        $stores = [];
        foreach ([0, ...$storeIds] as $storeId) {
            $store = $this->createMock(StoreInterface::class);
            $store->method('getId')->willReturn($storeId);
            $stores[] = $store;
        }
        $storeManager = $this->createMock(StoreManagerInterface::class);
        $storeManager->method('getStores')->with(true)->willReturn($stores);

        return $storeManager;
    }

    /**
     * A logger that keeps every record
     *
     * @return AbstractLogger&object{records: list<array{level: mixed, message: string, context: array<mixed>}>}
     */
    private function logger(): AbstractLogger
    {
        return new class extends AbstractLogger {
            /**
             * @var list<array{level: mixed, message: string, context: array<mixed>}>
             */
            public array $records = [];

            public function log($level, string|\Stringable $message, array $context = []): void
            {
                $this->records[] = ['level' => $level, 'message' => (string) $message, 'context' => $context];
            }
        };
    }

    /**
     * A repository holding snippets set for store views, whose save fails for the codes given
     *
     * @param array<string, list<int>> $snippets store IDs by code
     * @param list<string> $failing
     * @return SnippetRootRepositoryInterface&object{saved: array<string, list<int>>}
     */
    private function repository(array $snippets, array $failing): SnippetRootRepositoryInterface
    {
        $stored = [];
        $id = 0;
        foreach ($snippets as $code => $storeIds) {
            $stored[] = new Snippet(
                ++$id,
                $code,
                ucfirst($code),
                '',
                '',
                false,
                true,
                ['product'],
                (new TreeFactoryFake())->tree(),
                $storeIds,
                null,
                0,
                null,
                ''
            );
        }

        return new class ($stored, $failing) implements SnippetRootRepositoryInterface {
            /**
             * @var array<string, list<int>>
             */
            public array $saved = [];

            /**
             * @param list<SnippetRootInterface> $snippets
             * @param list<string> $failing
             */
            public function __construct(private readonly array $snippets, private readonly array $failing)
            {
            }

            public function get(int $id): SnippetRootInterface
            {
                throw new \LogicException('Not read by id.');
            }

            public function getByCode(string $code): SnippetRootInterface
            {
                throw new \LogicException('Not read by code.');
            }

            public function save(SnippetInterface $snippet): SnippetRootInterface
            {
                if (in_array($snippet->getCode(), $this->failing, true)) {
                    throw new \RuntimeException('The database went away.');
                }
                $this->saved[$snippet->getCode()] = $snippet->getStoreIds();
                foreach ($this->snippets as $stored) {
                    if ($stored->getCode() === $snippet->getCode()) {
                        return $stored;
                    }
                }
                throw new \LogicException('Not stored.');
            }

            public function list(
                ?int $storeId = null,
                ?string $pageKind = null,
                ?bool $active = null,
                ?bool $block = null
            ): array {
                return $this->snippets;
            }

            public function delete(SnippetInterface $snippet): void
            {
                throw new \LogicException('Not deleted.');
            }
        };
    }
}
