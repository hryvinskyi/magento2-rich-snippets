<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Check;

use Hryvinskyi\RichSnippets\Api\Data\FindingInterface;
use Hryvinskyi\RichSnippets\Api\Data\PropertyTone;

/**
 * One gap in the shop's data, as the last finished run counted it
 */
class Finding implements FindingInterface
{
    /**
     * @param string $key
     * @param PropertyTone $tone
     * @param string $ruleCode
     * @param string $path empty when the whole result is left out
     * @param list<string> $sources
     * @param list<string> $snippetCodes
     * @param int $pages
     * @param array<string,int> $pagesByKind
     */
    public function __construct(
        private readonly string $key,
        private readonly PropertyTone $tone,
        private readonly string $ruleCode,
        private readonly string $path,
        private readonly array $sources,
        private readonly array $snippetCodes,
        private readonly int $pages,
        private readonly array $pagesByKind
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getKey(): string
    {
        return $this->key;
    }

    /**
     * @inheritDoc
     */
    public function getTone(): PropertyTone
    {
        return $this->tone;
    }

    /**
     * @inheritDoc
     */
    public function getRuleCode(): string
    {
        return $this->ruleCode;
    }

    /**
     * @inheritDoc
     */
    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * @inheritDoc
     */
    public function getSources(): array
    {
        return $this->sources;
    }

    /**
     * @inheritDoc
     */
    public function getSnippetCodes(): array
    {
        return $this->snippetCodes;
    }

    /**
     * @inheritDoc
     */
    public function getPages(): int
    {
        return $this->pages;
    }

    /**
     * @inheritDoc
     */
    public function getPagesByKind(): array
    {
        return $this->pagesByKind;
    }

    /**
     * @inheritDoc
     */
    public function isResultLeftOut(): bool
    {
        return $this->path === '';
    }
}
