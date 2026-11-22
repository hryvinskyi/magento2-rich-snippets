<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Listing;

use Hryvinskyi\RichSnippets\Api\Data\SnippetFlagInterface;
use Hryvinskyi\RichSnippets\Api\Data\SnippetInterface;
use Hryvinskyi\RichSnippets\Api\Data\SnippetRowInterface;

/**
 * One row of the snippet list
 */
class SnippetRow implements SnippetRowInterface
{
    /**
     * @param SnippetInterface $snippet
     * @param string $conditionSummary
     * @param list<string> $storeNames
     * @param int $propertyCount
     * @param array<string,string|null> $uses
     * @param array<string,string> $usedIn
     * @param list<SnippetFlagInterface> $flags
     */
    public function __construct(
        private readonly SnippetInterface $snippet,
        private readonly string $conditionSummary,
        private readonly array $storeNames,
        private readonly int $propertyCount,
        private readonly array $uses,
        private readonly array $usedIn,
        private readonly array $flags
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getSnippet(): SnippetInterface
    {
        return $this->snippet;
    }

    /**
     * @inheritDoc
     */
    public function getConditionSummary(): string
    {
        return $this->conditionSummary;
    }

    /**
     * @inheritDoc
     */
    public function getStoreNames(): array
    {
        return $this->storeNames;
    }

    /**
     * @inheritDoc
     */
    public function getPropertyCount(): int
    {
        return $this->propertyCount;
    }

    /**
     * @inheritDoc
     */
    public function getUses(): array
    {
        return $this->uses;
    }

    /**
     * @inheritDoc
     */
    public function getUsedIn(): array
    {
        return $this->usedIn;
    }

    /**
     * @inheritDoc
     */
    public function getFlags(): array
    {
        return $this->flags;
    }
}
