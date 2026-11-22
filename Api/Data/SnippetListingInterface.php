<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Api\Data;

/**
 * The snippet list of a store view or of every store view: its rows and the counts its chips and subtitle show
 *
 * @api
 */
interface SnippetListingInterface
{
    /**
     * The rows, by sort order, then ID
     *
     * @return list<SnippetRowInterface>
     */
    public function getRows(): array;

    /**
     * How many page snippets are set for each kind of page, switched on or not
     *
     * @return array<string,int> by kind code, every kind present
     */
    public function getKindCounts(): array;

    /**
     * How many rows carry at least one flag of each tone
     *
     * @return array<string,int> by tone, every tone present
     */
    public function getFlagCounts(): array;

    /**
     * How many rows need attention: those with a bad or a warning flag
     *
     * @return int
     */
    public function getAttentionCount(): int;

    /**
     * How many page snippets are switched on
     *
     * @return int
     */
    public function getOnCount(): int;

    /**
     * How many page snippets are switched off
     *
     * @return int
     */
    public function getOffCount(): int;

    /**
     * How many building blocks are listed
     *
     * @return int
     */
    public function getBlockCount(): int;
}
