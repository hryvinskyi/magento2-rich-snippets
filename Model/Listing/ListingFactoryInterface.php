<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Listing;

use Hryvinskyi\RichSnippets\Api\Data\SnippetFlagInterface;
use Hryvinskyi\RichSnippets\Api\Data\SnippetInterface;
use Hryvinskyi\RichSnippets\Api\Data\SnippetListingInterface;
use Hryvinskyi\RichSnippets\Api\Data\SnippetRowInterface;

/**
 * Creates the snippet list and its rows
 */
interface ListingFactoryInterface
{
    /**
     * A row
     *
     * @param SnippetInterface $snippet
     * @param string $conditionSummary
     * @param list<string> $storeNames
     * @param int $propertyCount
     * @param array<string,string|null> $uses
     * @param array<string,string> $usedIn
     * @param list<SnippetFlagInterface> $flags
     * @return SnippetRowInterface
     */
    public function row(
        SnippetInterface $snippet,
        string $conditionSummary,
        array $storeNames,
        int $propertyCount,
        array $uses,
        array $usedIn,
        array $flags
    ): SnippetRowInterface;

    /**
     * The list
     *
     * @param list<SnippetRowInterface> $rows
     * @param array<string,int> $kindCounts
     * @param array<string,int> $flagCounts
     * @param int $attentionCount
     * @param int $onCount
     * @param int $offCount
     * @param int $blockCount
     * @return SnippetListingInterface
     */
    public function listing(
        array $rows,
        array $kindCounts,
        array $flagCounts,
        int $attentionCount,
        int $onCount,
        int $offCount,
        int $blockCount
    ): SnippetListingInterface;
}
