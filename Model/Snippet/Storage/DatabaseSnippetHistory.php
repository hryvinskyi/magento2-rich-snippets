<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Snippet\Storage;

use Hryvinskyi\RichSnippets\Model\ResourceModel\Snippet as SnippetResource;
use Hryvinskyi\RichSnippets\Model\Snippet\SnippetHistoryInterface;

/**
 * Reads when snippets were added from the time the database stamped on their rows, which it keeps in UTC
 */
class DatabaseSnippetHistory implements SnippetHistoryInterface
{
    /**
     * @param SnippetResource $resource
     */
    public function __construct(private readonly SnippetResource $resource)
    {
    }

    /**
     * @inheritDoc
     */
    public function firstAddedAt(array $snippetIds): ?int
    {
        $first = $this->resource->firstCreatedAt($snippetIds);
        $seconds = $first === null ? false : strtotime($first . ' UTC');

        return $seconds === false ? null : $seconds;
    }
}
