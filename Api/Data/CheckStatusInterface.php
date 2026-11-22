<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Api\Data;

/**
 * Where the catalog check stands: the run in progress, the last one that finished, and its counts
 *
 * The counts are those of the last finished run over every store view, each page counted once whatever the number
 * of store views it was checked in.
 *
 * @api
 */
interface CheckStatusInterface
{
    /**
     * The requested or running run; null when none is pending
     *
     * @return CheckRunInterface|null
     */
    public function getCurrent(): ?CheckRunInterface;

    /**
     * The last run that checked every page; null before one has
     *
     * @return CheckRunInterface|null
     */
    public function getLast(): ?CheckRunInterface;

    /**
     * The newest run when it failed after the last finished one; null otherwise
     *
     * @return CheckRunInterface|null
     */
    public function getFailed(): ?CheckRunInterface;

    /**
     * The pages the last run checked, by kind of page
     *
     * @return array<string,int>
     */
    public function getPagesByKind(): array;

    /**
     * The pages Google would report as invalid, by kind of page: a required property is missing
     *
     * @return array<string,int>
     */
    public function getInvalidPagesByKind(): array;

    /**
     * The valid pages that miss a recommended property, by kind of page
     *
     * @return array<string,int>
     */
    public function getGapPagesByKind(): array;
}
