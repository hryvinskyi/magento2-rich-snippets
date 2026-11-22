<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Placement;

use Hryvinskyi\RichSnippets\Api\Data\PlacementCountInterface;

/**
 * How many pages a placement reaches, of how many there are; refused when a number is negative or more pages match
 * than there are
 */
class PlacementCount implements PlacementCountInterface
{
    /**
     * @param int $matching
     * @param int $total
     * @throws \InvalidArgumentException
     */
    public function __construct(
        private readonly int $matching,
        private readonly int $total
    ) {
        if ($matching < 0 || $matching > $total) {
            throw new \InvalidArgumentException('A placement reaches between none and all of the pages.');
        }
    }

    /**
     * @inheritDoc
     */
    public function getMatching(): int
    {
        return $this->matching;
    }

    /**
     * @inheritDoc
     */
    public function getTotal(): int
    {
        return $this->total;
    }
}
