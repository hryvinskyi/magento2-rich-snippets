<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Library\Factory;

use Hryvinskyi\RichSnippets\Api\Data\AddedSnippetInterface;
use Hryvinskyi\RichSnippets\Api\Data\AddedSnippetInterfaceFactory;
use Hryvinskyi\RichSnippets\Api\Data\LibraryEntryInterface;
use Hryvinskyi\RichSnippets\Api\Data\LibraryEntryInterfaceFactory;
use Hryvinskyi\RichSnippets\Api\Data\LibraryTemplateInterface;
use Hryvinskyi\RichSnippets\Model\Library\LibraryResultFactoryInterface;

/**
 * Creates library entries and added snippets through the factories Magento generates for their interfaces
 */
class LibraryResultFactoryAdapter implements LibraryResultFactoryInterface
{
    /**
     * @param LibraryEntryInterfaceFactory $entries
     * @param AddedSnippetInterfaceFactory $added
     */
    public function __construct(
        private readonly LibraryEntryInterfaceFactory $entries,
        private readonly AddedSnippetInterfaceFactory $added
    ) {
    }

    /**
     * @inheritDoc
     */
    public function entry(
        LibraryTemplateInterface $template,
        array $addedVersions,
        array $blocksInShop,
        string $nextName
    ): LibraryEntryInterface {
        return $this->entries->create([
            'template' => $template,
            'addedVersions' => $addedVersions,
            'blocksInShop' => $blocksInShop,
            'nextName' => $nextName,
        ]);
    }

    /**
     * @inheritDoc
     */
    public function added(
        int $id,
        string $code,
        string $name,
        bool $block,
        string $template,
        bool $reused
    ): AddedSnippetInterface {
        return $this->added->create([
            'id' => $id,
            'code' => $code,
            'name' => $name,
            'block' => $block,
            'template' => $template,
            'reused' => $reused,
        ]);
    }
}
