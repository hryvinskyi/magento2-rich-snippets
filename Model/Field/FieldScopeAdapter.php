<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Field;

use Hryvinskyi\JsonTemplate\Api\ScopeInterface;
use Hryvinskyi\JsonTemplate\Api\VariableProviderInterface;
use Hryvinskyi\RichSnippets\Model\Field\Subject\RootSubjectInterface;
use Hryvinskyi\RichSnippets\Model\Field\Value\FieldValuesFactory;
use Hryvinskyi\RichSnippets\Model\Field\Value\PageContextValue;

/**
 * Gives the engine a root's values on the page the scope evaluates for: lazy values of the entity the root stands
 * for, or nothing when it stands for none on that page (no `product` on a category page) or the scope has no page
 */
class FieldScopeAdapter implements VariableProviderInterface
{
    /**
     * @param RootSubjectInterface $subject
     * @param FieldValuesFactory $values
     */
    public function __construct(
        private readonly RootSubjectInterface $subject,
        private readonly FieldValuesFactory $values
    ) {
    }

    /**
     * @inheritDoc
     */
    public function provide(string $root, ScopeInterface $scope): mixed
    {
        $context = $scope->get(PageContextValue::ROOT);
        if (!$context instanceof PageContextValue) {
            return null;
        }
        $page = $context->getPage();
        $subjectId = $this->subject->subjectOf($page);

        return $subjectId === null
            ? null
            : $this->values->create(['page' => $page, 'subjectId' => $subjectId, 'prefix' => $root]);
    }
}
