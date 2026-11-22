<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Api;

use Hryvinskyi\RichSnippets\Api\Data\OverviewReportInterface;
use Magento\Framework\Exception\NoSuchEntityException;

/**
 * The module's landing page: whether anything is output, what each kind of page carries, how much of the shop has
 * what each kind of Google result needs, and what wants fixing
 *
 * It says what the shop writes; it never claims to know what Google shows.
 *
 * @api
 */
interface OverviewInterface
{
    /**
     * The overview of a store view, or of every store view
     *
     * @param int|null $storeId null for every store view
     * @return OverviewReportInterface
     * @throws NoSuchEntityException when the shop has no store view with the ID
     */
    public function read(?int $storeId): OverviewReportInterface;
}
