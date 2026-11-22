<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Snippet;

use Hryvinskyi\RichSnippets\Api\Data\SnippetInterface;

/**
 * Cleans the cached storefront pages a change of a snippet reaches, once the change is committed
 */
interface PageCacheCleanerInterface
{
    /**
     * Schedule the cleaning of the pages a change reaches for when the current transaction commits
     *
     * The pages are those of the snippet's placement before and after the change, while it is switched on, and those
     * of every snippet that includes it, directly or through others, while that one is switched on. A change that
     * reaches no page cleans nothing.
     *
     * @param SnippetInterface|null $before the stored snippet before the change; null for a new one
     * @param SnippetInterface|null $after the snippet after the change; null for a deleted one
     * @param array<string,list<string>> $edges the codes each stored snippet includes, by code
     * @return void
     */
    public function cleanAfterCommit(?SnippetInterface $before, ?SnippetInterface $after, array $edges): void;
}
