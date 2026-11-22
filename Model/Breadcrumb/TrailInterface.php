<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Breadcrumb;

use Hryvinskyi\RichSnippets\Api\Data\PageEntityInterface;

/**
 * The breadcrumb levels of one kind of page below the home page
 */
interface TrailInterface
{
    /**
     * The levels, top first, the page itself last
     *
     * @param PageEntityInterface $page
     * @param int $subjectId the page's entity
     * @return list<array{name: string, item: string}>
     */
    public function trail(PageEntityInterface $page, int $subjectId): array;
}
