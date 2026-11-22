<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Api\Data;

/**
 * How many pages a placement reaches, of how many there are
 *
 * @api
 */
interface PlacementCountInterface
{
    /**
     * The pages of the placement's kinds that meet its conditions
     *
     * @return int
     */
    public function getMatching(): int;

    /**
     * The pages of the placement's kinds
     *
     * @return int
     */
    public function getTotal(): int;
}
