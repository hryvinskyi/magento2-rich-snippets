<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Check;

use Hryvinskyi\RichSnippets\Api\Data\FindingInterface;

/**
 * Reads what a catalog check found and counted
 *
 * Without a store view, a finding is the findings of every store view with the same key: their snippets together,
 * and each page counted once.
 *
 * @phpstan-type ItemRow array{kind: string, entity: int, store: int}
 */
interface CheckResultReaderInterface
{
    /**
     * A run's findings, in no particular order
     *
     * @param int $runId
     * @param int|null $storeId null for every store view
     * @return list<FindingInterface>
     */
    public function findings(int $runId, ?int $storeId): array;

    /**
     * A run's finding with a key
     *
     * @param int $runId
     * @param string $key
     * @param int|null $storeId null for every store view
     * @return FindingInterface|null null when the run has no such finding
     */
    public function finding(int $runId, string $key, ?int $storeId): ?FindingInterface;

    /**
     * Part of the pages of a finding, by kind of page, then entity; each with the first store view it is in
     *
     * @param int $runId
     * @param string $key
     * @param int|null $storeId null for every store view
     * @param int $offset
     * @param int $limit
     * @return list<ItemRow> the entity is 0 for a page that shows none
     */
    public function items(int $runId, string $key, ?int $storeId, int $offset, int $limit): array;

    /**
     * A run's counts of a store view, or of every store view (0)
     *
     * @param int $runId
     * @param int $storeId
     * @return array<string, array<string, int>> by metric, then subject
     */
    public function aggregates(int $runId, int $storeId): array;
}
