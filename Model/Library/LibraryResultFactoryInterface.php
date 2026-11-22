<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Library;

use Hryvinskyi\RichSnippets\Api\Data\AddedSnippetInterface;
use Hryvinskyi\RichSnippets\Api\Data\LibraryEntryInterface;
use Hryvinskyi\RichSnippets\Api\Data\LibraryTemplateInterface;

/**
 * Creates what the library answers: its entries and the snippets an add ended with
 */
interface LibraryResultFactoryInterface
{
    /**
     * A library template with what the shop has of it
     *
     * @param LibraryTemplateInterface $template
     * @param list<int> $addedVersions
     * @param list<string> $blocksInShop
     * @param string $nextName
     * @return LibraryEntryInterface
     */
    public function entry(
        LibraryTemplateInterface $template,
        array $addedVersions,
        array $blocksInShop,
        string $nextName
    ): LibraryEntryInterface;

    /**
     * A snippet an add ended with
     *
     * @param int $id
     * @param string $code
     * @param string $name
     * @param bool $block
     * @param string $template
     * @param bool $reused
     * @return AddedSnippetInterface
     */
    public function added(
        int $id,
        string $code,
        string $name,
        bool $block,
        string $template,
        bool $reused
    ): AddedSnippetInterface;
}
