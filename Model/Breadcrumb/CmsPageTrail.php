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
 * A CMS page's one level: its title and address
 */
class CmsPageTrail implements TrailInterface
{
    /**
     * @param EntityDataSourceInterface $pages
     * @param string $part the CMS page source's part holding each page's `title` and `url`
     */
    public function __construct(
        private readonly EntityDataSourceInterface $pages,
        private readonly string $part = 'page'
    ) {
    }

    /**
     * @inheritDoc
     */
    public function trail(PageEntityInterface $page, int $subjectId): array
    {
        $entry = $this->pages->get($subjectId, $page->getStoreId(), $this->part);
        if (!is_array($entry) || !is_string($entry['title'] ?? null) || !is_string($entry['url'] ?? null)) {
            return [];
        }

        return $entry['title'] === '' ? [] : [['name' => $entry['title'], 'item' => $entry['url']]];
    }
}
