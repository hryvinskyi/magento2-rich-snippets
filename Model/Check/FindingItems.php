<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Check;

use Hryvinskyi\RichSnippets\Api\Data\FindingInterface;
use Hryvinskyi\RichSnippets\Api\Data\FindingItemInterface;
use Hryvinskyi\RichSnippets\Api\Data\FindingItemsInterface;

/**
 * Part of the list of pages behind a finding
 */
class FindingItems implements FindingItemsInterface
{
    /**
     * @param FindingInterface $finding
     * @param int $total
     * @param list<FindingItemInterface> $items
     */
    public function __construct(
        private readonly FindingInterface $finding,
        private readonly int $total,
        private readonly array $items
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getFinding(): FindingInterface
    {
        return $this->finding;
    }

    /**
     * @inheritDoc
     */
    public function getTotal(): int
    {
        return $this->total;
    }

    /**
     * @inheritDoc
     */
    public function getItems(): array
    {
        return $this->items;
    }
}
