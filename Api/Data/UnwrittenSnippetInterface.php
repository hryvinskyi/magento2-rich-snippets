<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Api\Data;

/**
 * A snippet set for a page that the page, as it was read, does not carry, and why
 *
 * @api
 */
interface UnwrittenSnippetInterface
{
    /**
     * The snippet
     *
     * @return SnippetInterface
     */
    public function getSnippet(): SnippetInterface;

    /**
     * Why the page does not carry it
     *
     * @return UnwrittenReason
     */
    public function getReason(): UnwrittenReason;
}
