<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Api\Data;

/**
 * A library template as the shop sees it: whether a copy of it was added, and whether the library has a newer
 * version than a copy
 *
 * @api
 */
interface LibraryEntryInterface
{
    /**
     * The template
     *
     * @return LibraryTemplateInterface
     */
    public function getTemplate(): LibraryTemplateInterface;

    /**
     * Whether the shop has a snippet added from the template
     *
     * @return bool
     */
    public function isAdded(): bool;

    /**
     * The template versions the shop's copies were added from, lowest first, each once
     *
     * @return list<int>
     */
    public function getAddedVersions(): array;

    /**
     * Whether a copy was added from a version older than the template's
     *
     * @return bool
     */
    public function hasNewerVersion(): bool;

    /**
     * The library codes of the building blocks the template uses that the shop has already, so adding reuses them
     *
     * @return list<string>
     */
    public function getBlocksInShop(): array;

    /**
     * The name the next copy gets: the template's name, or with a number when a snippet has that name (`Product 2`)
     *
     * @return string
     */
    public function getNextName(): string;
}
