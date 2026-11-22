<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Check;

use Hryvinskyi\RichSnippets\Api\CatalogCheckRunnerInterface;
use Hryvinskyi\RichSnippets\Api\Data\CheckRunInterface;
use Hryvinskyi\RichSnippets\Api\FrontendEmulationInterface;
use Hryvinskyi\RichSnippets\Api\PageEntityFactoryInterface;
use Hryvinskyi\RichSnippets\Api\PageKindPoolInterface;
use Hryvinskyi\RichSnippets\Model\Clock\ClockInterface;
use Psr\Log\LoggerInterface;

/**
 * Runs a claimed check over every page that exists, store view by store view, kind by kind, batch by batch
 *
 * The pages are counted before the first batch, so progress can be shown from the start. Each store view is checked
 * inside its own storefront; its findings and their pages are written after every batch, together with the run's
 * progress, and its counts after its last batch. The counts over every store view count each page once where that
 * matters (pages, invalid pages, pages with a gap) and add up the store views' otherwise. A run that throws is
 * marked failed before the error goes on.
 */
class CatalogCheckRunner implements CatalogCheckRunnerInterface
{
    private const FAILURE = 'error';

    /**
     * @param CheckedStores $stores
     * @param PageKindPoolInterface $kinds
     * @param ExistingPageIds $pageIds
     * @param PageEntityFactoryInterface $pages
     * @param FrontendEmulationInterface $emulation
     * @param ChunkCheck $chunks
     * @param StoreTallyFactory $tallies
     * @param MergedAggregatesFactory $merged
     * @param CheckRunStoreInterface $runs
     * @param CheckResultWriterInterface $results
     * @param ClockInterface $clock
     * @param LoggerInterface $logger
     * @param int $chunkSize the pages checked in one batch
     */
    public function __construct(
        private readonly CheckedStores $stores,
        private readonly PageKindPoolInterface $kinds,
        private readonly ExistingPageIds $pageIds,
        private readonly PageEntityFactoryInterface $pages,
        private readonly FrontendEmulationInterface $emulation,
        private readonly ChunkCheck $chunks,
        private readonly StoreTallyFactory $tallies,
        private readonly MergedAggregatesFactory $merged,
        private readonly CheckRunStoreInterface $runs,
        private readonly CheckResultWriterInterface $results,
        private readonly ClockInterface $clock,
        private readonly LoggerInterface $logger,
        private readonly int $chunkSize = 200
    ) {
    }

    /**
     * @inheritDoc
     */
    public function run(CheckRunInterface $run): void
    {
        $runId = $run->getId();
        try {
            $this->check($runId);
            $this->runs->finish($runId, $this->clock->now());
        } catch (\Throwable $e) {
            $this->runs->fail($runId, self::FAILURE, $this->clock->now());
            $this->logger->error(
                sprintf('Rich snippets: the catalog check %d failed: %s', $runId, $e->getMessage()),
                ['exception' => $e]
            );
            throw $e;
        }
    }

    /**
     * Check every page of every store view and write the counts
     *
     * @param int $runId
     * @return void
     */
    private function check(int $runId): void
    {
        $storeIds = $this->stores->ids();
        $kinds = $this->kinds->getCodes();
        $total = 0;
        foreach ($storeIds as $storeId) {
            foreach ($kinds as $kind) {
                $total += $this->pageIds->count($kind, $storeId);
            }
        }
        $this->runs->start($runId, $total, count($storeIds), $this->clock->now());
        $this->chunks->startRun();

        $merged = $this->merged->create();
        $checked = 0;
        foreach ($storeIds as $storeId) {
            $tally = $this->tallies->create(['storeId' => $storeId]);
            $checked = $this->emulation->run(
                $storeId,
                fn (): int => $this->checkStore($runId, $tally, $kinds, $checked)
            );
            $rows = $tally->aggregates();
            $this->results->writeAggregates($runId, $storeId, $rows);
            $merged->add($rows);
        }
        $this->results->writeAggregates($runId, 0, $merged->rows());
        $distinct = [];
        foreach ($kinds as $kind) {
            $distinct[$kind] = $this->pageIds->distinct($kind, $storeIds);
        }
        $this->results->writeDistinctCounts($runId, array_filter($distinct));
    }

    /**
     * Check every page of a store view, batch by batch
     *
     * @param int $runId
     * @param StoreTally $tally
     * @param list<string> $kinds
     * @param int $checked the pages checked before this store view
     * @return int the pages checked after it
     */
    private function checkStore(int $runId, StoreTally $tally, array $kinds, int $checked): int
    {
        $storeId = $tally->getStoreId();
        foreach ($kinds as $kind) {
            foreach (array_chunk($this->pageIds->ids($kind, $storeId), max(1, $this->chunkSize)) as $ids) {
                $batch = array_map(
                    fn (?int $id) => $this->pages->create($kind, $id, $storeId),
                    $ids
                );
                $this->chunks->check($kind, $storeId, $batch, $tally);
                $this->results->writeFindings($runId, $tally);
                $checked += count($batch);
                $this->runs->progress($runId, $checked, $this->clock->now());
            }
        }

        return $checked;
    }
}
