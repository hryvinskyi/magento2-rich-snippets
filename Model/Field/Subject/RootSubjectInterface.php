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
 * The entity a root stands for on a page (the product of a product page, the store view of any page)
 */
interface RootSubjectInterface
{
    /**
     * The entity's ID; null when the root stands for nothing on the page
     *
     * @param PageEntityInterface $page
     * @return int|null
     */
    public function subjectOf(PageEntityInterface $page): ?int;
}
