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
 * The page's own entity, on the kinds of page that show one of the root's kind
 */
class PageEntitySubject implements RootSubjectInterface
{
    /**
     * @param list<string> $pageKinds
     */
    public function __construct(private readonly array $pageKinds = [])
    {
    }

    /**
     * @inheritDoc
     */
    public function subjectOf(PageEntityInterface $page): ?int
    {
        return in_array($page->getKind(), $this->pageKinds, true) ? $page->getEntityId() : null;
    }
}
