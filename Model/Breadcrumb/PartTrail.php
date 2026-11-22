<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Breadcrumb;

use Hryvinskyi\RichSnippets\Api\Data\PageEntityInterface;
use Hryvinskyi\RichSnippets\Api\EntityDataSourceInterface;

/**
 * A page's levels as a part of its entity source holds them (a category's path in the tree)
 */
class PartTrail implements TrailInterface
{
    /**
     * @param EntityDataSourceInterface $source
     * @param string $part
     */
    public function __construct(
        private readonly EntityDataSourceInterface $source,
        private readonly string $part
    ) {
    }

    /**
     * @inheritDoc
     */
    public function trail(PageEntityInterface $page, int $subjectId): array
    {
        $levels = $this->source->get($subjectId, $page->getStoreId(), $this->part);
        $crumbs = [];
        foreach (is_array($levels) ? $levels : [] as $level) {
            if (is_array($level) && is_string($level['name'] ?? null) && is_string($level['item'] ?? null)) {
                $crumbs[] = ['name' => $level['name'], 'item' => $level['item']];
            }
        }

        return $crumbs;
    }
}
