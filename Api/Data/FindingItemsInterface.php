<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Api\Data;

/**
 * One page of the list of pages behind a finding
 *
 * @api
 */
interface FindingItemsInterface
{
    /**
     * The finding the pages are behind
     *
     * @return FindingInterface
     */
    public function getFinding(): FindingInterface;

    /**
     * Every page behind the finding
     *
     * @return int
     */
    public function getTotal(): int;

    /**
     * The pages of this part of the list, in the list's order
     *
     * @return list<FindingItemInterface>
     */
    public function getItems(): array;
}
