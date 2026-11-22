<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Library;

use Hryvinskyi\RichSnippets\Api\Data\LibraryEntryInterface;
use Hryvinskyi\RichSnippets\Api\Data\LibraryTemplateInterface;

/**
 * A library template with what the shop has of it
 */
class LibraryEntry implements LibraryEntryInterface
{
    /**
     * @param LibraryTemplateInterface $template
     * @param list<int> $addedVersions
     * @param list<string> $blocksInShop
     * @param string $nextName
     */
    public function __construct(
        private readonly LibraryTemplateInterface $template,
        private readonly array $addedVersions,
        private readonly array $blocksInShop,
        private readonly string $nextName
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getTemplate(): LibraryTemplateInterface
    {
        return $this->template;
    }

    /**
     * @inheritDoc
     */
    public function isAdded(): bool
    {
        return $this->addedVersions !== [];
    }

    /**
     * @inheritDoc
     */
    public function getAddedVersions(): array
    {
        return $this->addedVersions;
    }

    /**
     * @inheritDoc
     */
    public function hasNewerVersion(): bool
    {
        return $this->addedVersions !== [] && min($this->addedVersions) < $this->template->getVersion();
    }

    /**
     * @inheritDoc
     */
    public function getBlocksInShop(): array
    {
        return $this->blocksInShop;
    }

    /**
     * @inheritDoc
     */
    public function getNextName(): string
    {
        return $this->nextName;
    }
}
