<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Listing\Factory;

use Hryvinskyi\RichSnippets\Api\Data\SnippetInterface;
use Hryvinskyi\RichSnippets\Api\Data\SnippetListingInterface;
use Hryvinskyi\RichSnippets\Api\Data\SnippetListingInterfaceFactory;
use Hryvinskyi\RichSnippets\Api\Data\SnippetRowInterface;
use Hryvinskyi\RichSnippets\Api\Data\SnippetRowInterfaceFactory;
use Hryvinskyi\RichSnippets\Model\Listing\ListingFactoryInterface;

/**
 * Creates the snippet list and its rows through the factories Magento generates for their interfaces
 */
class ListingFactoryAdapter implements ListingFactoryInterface
{
    /**
     * @param SnippetRowInterfaceFactory $rows
     * @param SnippetListingInterfaceFactory $listings
     */
    public function __construct(
        private readonly SnippetRowInterfaceFactory $rows,
        private readonly SnippetListingInterfaceFactory $listings
    ) {
    }

    /**
     * @inheritDoc
     */
    public function row(
        SnippetInterface $snippet,
        string $conditionSummary,
        array $storeNames,
        int $propertyCount,
        array $uses,
        array $usedIn,
        array $flags
    ): SnippetRowInterface {
        return $this->rows->create([
            'snippet' => $snippet,
            'conditionSummary' => $conditionSummary,
            'storeNames' => $storeNames,
            'propertyCount' => $propertyCount,
            'uses' => $uses,
            'usedIn' => $usedIn,
            'flags' => $flags,
        ]);
    }

    /**
     * @inheritDoc
     */
    public function listing(
        array $rows,
        array $kindCounts,
        array $flagCounts,
        int $attentionCount,
        int $onCount,
        int $offCount,
        int $blockCount
    ): SnippetListingInterface {
        return $this->listings->create([
            'rows' => $rows,
            'kindCounts' => $kindCounts,
            'flagCounts' => $flagCounts,
            'attentionCount' => $attentionCount,
            'onCount' => $onCount,
            'offCount' => $offCount,
            'blockCount' => $blockCount,
        ]);
    }
}
