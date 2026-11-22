<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Check;

use Hryvinskyi\RichSnippets\Api\CatalogCheckInterface;
use Hryvinskyi\RichSnippets\Api\Data\CheckRunInterface;
use Hryvinskyi\RichSnippets\Api\Data\CheckState;
use Hryvinskyi\RichSnippets\Api\Data\CheckStatusInterface;
use Hryvinskyi\RichSnippets\Api\Data\CheckStatusInterfaceFactory;
use Hryvinskyi\RichSnippets\Api\Data\CheckTrigger;
use Hryvinskyi\RichSnippets\Api\Data\FindingInterface;
use Hryvinskyi\RichSnippets\Api\Data\FindingItemsInterface;
use Hryvinskyi\RichSnippets\Api\Data\FindingItemsInterfaceFactory;
use Hryvinskyi\RichSnippets\Api\Data\FindingsCsvInterface;
use Hryvinskyi\RichSnippets\Api\Data\FindingsCsvInterfaceFactory;
use Hryvinskyi\RichSnippets\Api\Data\PropertyTone;
use Hryvinskyi\RichSnippets\Model\Clock\ClockInterface;
use Magento\Framework\Exception\NoSuchEntityException;

/**
 * Asks for catalog checks and reads what the last finished one found
 *
 * The findings come worst first: bad, then warn, then note; within a tone the finding with the most pages first,
 * then by key. The pages behind a finding are listed a fixed number at a time; the CSV file has all of them, read
 * in batches so a finding of a large catalog never sits in memory as objects all at once.
 */
class CatalogCheck implements CatalogCheckInterface
{
    private const CSV_BATCH = 500;
    private const FILE_NAME = 'rich-snippets-%s.csv';

    /**
     * @param CheckRunStoreInterface $runs
     * @param CheckResultReaderInterface $results
     * @param FindingItemLabels $labels
     * @param FindingsCsvContent $csv
     * @param CheckStatusInterfaceFactory $statuses
     * @param FindingItemsInterfaceFactory $itemLists
     * @param FindingsCsvInterfaceFactory $files
     * @param ClockInterface $clock
     * @param int $pageSize the pages of a finding listed at a time
     */
    public function __construct(
        private readonly CheckRunStoreInterface $runs,
        private readonly CheckResultReaderInterface $results,
        private readonly FindingItemLabels $labels,
        private readonly FindingsCsvContent $csv,
        private readonly CheckStatusInterfaceFactory $statuses,
        private readonly FindingItemsInterfaceFactory $itemLists,
        private readonly FindingsCsvInterfaceFactory $files,
        private readonly ClockInterface $clock,
        private readonly int $pageSize = 8
    ) {
    }

    /**
     * @inheritDoc
     */
    public function request(CheckTrigger $trigger): CheckRunInterface
    {
        return $this->runs->request($trigger, $this->clock->now());
    }

    /**
     * @inheritDoc
     */
    public function status(): CheckStatusInterface
    {
        $last = $this->runs->latest(CheckState::Done);
        $newest = $this->runs->newest();
        $failed = $newest !== null && $newest->getState() === CheckState::Failed
            && ($last === null || $newest->getId() > $last->getId()) ? $newest : null;
        $counts = $last === null ? [] : $this->results->aggregates($last->getId(), 0);

        return $this->statuses->create([
            'current' => $this->runs->pending(),
            'last' => $last,
            'failed' => $failed,
            'pagesByKind' => $counts[AggregateMetric::Pages->value] ?? [],
            'invalidPagesByKind' => $counts[AggregateMetric::InvalidPages->value] ?? [],
            'gapPagesByKind' => $counts[AggregateMetric::GapPages->value] ?? [],
        ]);
    }

    /**
     * @inheritDoc
     */
    public function findings(?int $storeId): array
    {
        $last = $this->runs->latest(CheckState::Done);
        if ($last === null) {
            return [];
        }
        $findings = $this->results->findings($last->getId(), $storeId);
        usort($findings, fn (FindingInterface $a, FindingInterface $b): int => [
            $this->rank($a->getTone()),
            -$a->getPages(),
            $a->getKey(),
        ] <=> [
            $this->rank($b->getTone()),
            -$b->getPages(),
            $b->getKey(),
        ]);

        return $findings;
    }

    /**
     * @inheritDoc
     */
    public function findingItems(string $key, ?int $storeId, int $page): FindingItemsInterface
    {
        if ($page < 1) {
            throw new \InvalidArgumentException('A list of pages starts at its first part.');
        }
        [$runId, $finding] = $this->finding($key, $storeId);
        $rows = $this->results->items($runId, $key, $storeId, ($page - 1) * $this->pageSize, $this->pageSize);

        return $this->itemLists->create([
            'finding' => $finding,
            'total' => $finding->getPages(),
            'items' => $this->labels->items($rows),
        ]);
    }

    /**
     * @inheritDoc
     */
    public function exportCsv(string $key, ?int $storeId): FindingsCsvInterface
    {
        [$runId, $finding] = $this->finding($key, $storeId);
        $written = $this->csv->write($this->batches($runId, $finding->getKey(), $storeId));

        return $this->files->create([
            'fileName' => sprintf(self::FILE_NAME, $finding->getKey()),
            'content' => $written['content'],
            'rows' => $written['rows'],
        ]);
    }

    /**
     * The pages behind a finding, named, a batch at a time
     *
     * @param int $runId
     * @param string $key
     * @param int|null $storeId
     * @return \Generator<int, list<\Hryvinskyi\RichSnippets\Api\Data\FindingItemInterface>>
     */
    private function batches(int $runId, string $key, ?int $storeId): \Generator
    {
        for ($offset = 0;; $offset += self::CSV_BATCH) {
            $rows = $this->results->items($runId, $key, $storeId, $offset, self::CSV_BATCH);
            if ($rows === []) {
                return;
            }
            yield $this->labels->items($rows);
            if (count($rows) < self::CSV_BATCH) {
                return;
            }
        }
    }

    /**
     * The last finished run's finding with a key
     *
     * @param string $key
     * @param int|null $storeId
     * @return array{int, FindingInterface} the run's ID and the finding
     * @throws NoSuchEntityException
     */
    private function finding(string $key, ?int $storeId): array
    {
        $last = $this->runs->latest(CheckState::Done);
        $finding = $last === null ? null : $this->results->finding($last->getId(), $key, $storeId);
        if ($last === null || $finding === null) {
            throw new NoSuchEntityException(__('The last catalog check found nothing with the key "%1".', $key));
        }

        return [$last->getId(), $finding];
    }

    /**
     * Where a tone sorts: the worst first
     *
     * @param PropertyTone $tone
     * @return int
     */
    private function rank(PropertyTone $tone): int
    {
        return match ($tone) {
            PropertyTone::Bad => 0,
            PropertyTone::Warn => 1,
            PropertyTone::Note => 2,
        };
    }
}
