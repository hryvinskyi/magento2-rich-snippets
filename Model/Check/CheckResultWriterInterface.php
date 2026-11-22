<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Check;

/**
 * Writes what a running catalog check found and counted
 *
 * @phpstan-import-type Aggregate from StoreTally
 */
interface CheckResultWriterInterface
{
    /**
     * Write a store view's findings as they stand, with the pages collected since the last write
     *
     * A finding written before is replaced by its new counts; a page written before for a finding is kept once.
     *
     * @param int $runId
     * @param StoreTally $tally its pages are taken
     * @return void
     */
    public function writeFindings(int $runId, StoreTally $tally): void;

    /**
     * Write counts of a store view, or of every store view (0)
     *
     * @param int $runId
     * @param int $storeId
     * @param list<Aggregate> $rows
     * @return void
     */
    public function writeAggregates(int $runId, int $storeId, array $rows): void;

    /**
     * Write the counts over every store view that count each page once
     *
     * The pages by kind as given; the invalid pages and the pages with a gap as the written findings' pages say.
     *
     * @param int $runId
     * @param array<string,int> $pagesByKind each page once, whatever the number of store views it is in
     * @return void
     */
    public function writeDistinctCounts(int $runId, array $pagesByKind): void;
}
