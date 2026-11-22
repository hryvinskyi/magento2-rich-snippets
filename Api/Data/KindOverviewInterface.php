<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Api\Data;

/**
 * One kind of page on the Overview's page map: how many pages it has and the page snippets set for it
 *
 * @api
 */
interface KindOverviewInterface
{
    /**
     * The kind's code (`product`)
     *
     * @return string
     */
    public function getKind(): string;

    /**
     * The pages of the kind; null for a kind whose pages cannot be listed (every other page)
     *
     * In one store view, its pages; over every store view, each page once.
     *
     * @return int|null
     */
    public function getPageCount(): ?int;

    /**
     * The entity one page of the kind shows, to look at one
     *
     * Null when the kind has no page to show, or its pages show no entity.
     *
     * @return int|null
     */
    public function getSampleEntityId(): ?int;

    /**
     * The page snippets set for the kind in the scope, switched on or off, by sort order, then ID
     *
     * @return list<KindSnippetInterface>
     */
    public function getSnippets(): array;
}
