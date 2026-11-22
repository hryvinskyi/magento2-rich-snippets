<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Field\Subject;

use Hryvinskyi\RichSnippets\Api\Data\PageEntityInterface;

/**
 * The page's store view, on every page
 */
class StoreSubject implements RootSubjectInterface
{
    /**
     * @inheritDoc
     */
    public function subjectOf(PageEntityInterface $page): ?int
    {
        return $page->getStoreId();
    }
}
