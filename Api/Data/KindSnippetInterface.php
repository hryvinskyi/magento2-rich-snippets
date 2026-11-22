<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Api\Data;

/**
 * A page snippet set for a kind of page, as the Overview's page map shows it
 *
 * @api
 */
interface KindSnippetInterface
{
    /**
     * The snippet
     *
     * @return SnippetInterface
     */
    public function getSnippet(): SnippetInterface;

    /**
     * The pages of the kind that meet the snippet's conditions
     *
     * Null when the snippet has no condition, or the kind's pages cannot be listed.
     * Counted in the store view the Overview is about; over every store view, in the default store view when the
     * snippet is set for it, else in the first store view it is set for.
     *
     * @return PlacementCountInterface|null
     */
    public function getPlacementCount(): ?PlacementCountInterface;
}
