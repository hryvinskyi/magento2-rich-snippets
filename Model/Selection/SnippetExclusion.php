<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Selection;

use Hryvinskyi\RichSnippets\Api\Data\ConditionMismatchInterface;
use Hryvinskyi\RichSnippets\Api\Data\ExclusionReason;
use Hryvinskyi\RichSnippets\Api\Data\SnippetExclusionInterface;
use Hryvinskyi\RichSnippets\Api\Data\SnippetInterface;

/**
 * A page snippet not output on a page, with the reason and, for unmet conditions, the conditions that fail
 */
class SnippetExclusion implements SnippetExclusionInterface
{
    /**
     * @param SnippetInterface $snippet
     * @param ExclusionReason $reason
     * @param list<ConditionMismatchInterface> $mismatches
     */
    public function __construct(
        private readonly SnippetInterface $snippet,
        private readonly ExclusionReason $reason,
        private readonly array $mismatches = []
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
    public function getReason(): ExclusionReason
    {
        return $this->reason;
    }

    /**
     * @inheritDoc
     */
    public function getMismatches(): array
    {
        return $this->mismatches;
    }
}
