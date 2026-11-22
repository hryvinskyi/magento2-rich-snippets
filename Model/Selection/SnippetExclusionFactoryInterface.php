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
 * Creates the description of a page snippet not output on a page
 */
interface SnippetExclusionFactoryInterface
{
    /**
     * A snippet, the reason it is not output and the conditions that fail
     *
     * @param SnippetInterface $snippet
     * @param ExclusionReason $reason
     * @param list<ConditionMismatchInterface> $mismatches
     * @return SnippetExclusionInterface
     */
    public function create(
        SnippetInterface $snippet,
        ExclusionReason $reason,
        array $mismatches = []
    ): SnippetExclusionInterface;
}
