<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Api;

use Hryvinskyi\RichSnippets\Api\Data\CheckRunInterface;
use Hryvinskyi\RichSnippets\Api\Data\CheckStatusInterface;
use Hryvinskyi\RichSnippets\Api\Data\CheckTrigger;
use Hryvinskyi\RichSnippets\Api\Data\FindingInterface;
use Hryvinskyi\RichSnippets\Api\Data\FindingItemsInterface;
use Hryvinskyi\RichSnippets\Api\Data\FindingsCsvInterface;
use Magento\Framework\Exception\NoSuchEntityException;

/**
 * The catalog check: building every switched-on snippet for every page in every store view, without visiting the
 * storefront, and listing the gaps in the shop's data it finds
 *
 * Asking for a check only records the request; the job that runs checks picks it up within a minute. The findings
 * read are those of the last run that checked every page, until the next one has.
 *
 * @api
 */
interface CatalogCheckInterface
{
    /**
     * Ask for a check
     *
     * Asking while a check is requested or running asks for nothing more: that run is answered.
     *
     * @param CheckTrigger $trigger
     * @return CheckRunInterface the requested or running run
     */
    public function request(CheckTrigger $trigger): CheckRunInterface;

    /**
     * Where the check stands
     *
     * @return CheckStatusInterface
     */
    public function status(): CheckStatusInterface;

    /**
     * The findings of the last finished run, worst first: bad, warn, note, then most pages first
     *
     * @param int|null $storeId one store view; null for every store view, each page counted once
     * @return list<FindingInterface> none before a run has finished
     */
    public function findings(?int $storeId): array;

    /**
     * Part of the list of the pages behind a finding of the last finished run, by kind of page, then entity
     *
     * @param string $key the finding's key
     * @param int|null $storeId one store view; null for every store view, each page once
     * @param int $page the part of the list, from 1
     * @return FindingItemsInterface
     * @throws NoSuchEntityException when the last finished run has no finding with the key
     * @throws \InvalidArgumentException when the part is below 1
     */
    public function findingItems(string $key, ?int $storeId, int $page): FindingItemsInterface;

    /**
     * Every page behind a finding of the last finished run, as a CSV file
     *
     * @param string $key the finding's key
     * @param int|null $storeId one store view; null for every store view, each page once
     * @return FindingsCsvInterface
     * @throws NoSuchEntityException when the last finished run has no finding with the key
     */
    public function exportCsv(string $key, ?int $storeId): FindingsCsvInterface;
}
