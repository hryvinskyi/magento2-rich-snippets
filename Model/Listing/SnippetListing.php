<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Listing;

use Hryvinskyi\RichSnippets\Api\Data\SnippetListingInterface;
use Hryvinskyi\RichSnippets\Api\Data\SnippetRowInterface;

/**
 * The snippet list with its counts
 */
class SnippetListing implements SnippetListingInterface
{
    /**
     * @param list<SnippetRowInterface> $rows
     * @param array<string,int> $kindCounts
     * @param array<string,int> $flagCounts
     * @param int $attentionCount
     * @param int $onCount
     * @param int $offCount
     * @param int $blockCount
     */
    public function __construct(
        private readonly array $rows,
        private readonly array $kindCounts,
        private readonly array $flagCounts,
        private readonly int $attentionCount,
        private readonly int $onCount,
        private readonly int $offCount,
        private readonly int $blockCount
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getRows(): array
    {
        return $this->rows;
    }

    /**
     * @inheritDoc
     */
    public function getKindCounts(): array
    {
        return $this->kindCounts;
    }

    /**
     * @inheritDoc
     */
    public function getFlagCounts(): array
    {
        return $this->flagCounts;
    }

    /**
     * @inheritDoc
     */
    public function getAttentionCount(): int
    {
        return $this->attentionCount;
    }

    /**
     * @inheritDoc
     */
    public function getOnCount(): int
    {
        return $this->onCount;
    }

    /**
     * @inheritDoc
     */
    public function getOffCount(): int
    {
        return $this->offCount;
    }

    /**
     * @inheritDoc
     */
    public function getBlockCount(): int
    {
        return $this->blockCount;
    }
}
