<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Snippet;

/**
 * When snippets were added
 */
interface SnippetHistoryInterface
{
    /**
     * When the first of some snippets was added, in Unix seconds
     *
     * @param list<int> $snippetIds
     * @return int|null null when none of them is stored
     */
    public function firstAddedAt(array $snippetIds): ?int;
}
