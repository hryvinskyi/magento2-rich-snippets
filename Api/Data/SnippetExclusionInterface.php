<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Api\Data;

/**
 * A page snippet that is not output on a page, and why
 *
 * @api
 */
interface SnippetExclusionInterface
{
    /**
     * The snippet
     *
     * @return SnippetInterface
     */
    public function getSnippet(): SnippetInterface;

    /**
     * Why it is not output
     *
     * @return ExclusionReason
     */
    public function getReason(): ExclusionReason;

    /**
     * The conditions the page does not meet, in the snippet's order; empty unless the reason is ConditionsNotMet
     *
     * With "all of these" they are the conditions that fail; with "any of these" every condition fails, so they are
     * all of them.
     *
     * @return list<ConditionMismatchInterface>
     */
    public function getMismatches(): array;
}
