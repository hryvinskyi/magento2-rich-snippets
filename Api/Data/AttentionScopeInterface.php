<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Api\Data;

/**
 * What the Overview knows of the shop when it asks what wants fixing: the store view it is about and what the shop
 * has set up there
 *
 * @api
 */
interface AttentionScopeInterface
{
    /**
     * The store view; null for every store view
     *
     * @return int|null
     */
    public function getStoreId(): ?int;

    /**
     * How far the shop has set up its snippets in the scope
     *
     * @return SetupState
     */
    public function getSetupState(): SetupState;

    /**
     * Whether the theme's own Product markup is still on the product pages of the scope
     *
     * @return ThemeMarkupState
     */
    public function getThemeMarkup(): ThemeMarkupState;

    /**
     * The page snippets output in the scope, switched on or off, by sort order, then ID
     *
     * @return list<SnippetInterface>
     */
    public function getSnippets(): array;
}
