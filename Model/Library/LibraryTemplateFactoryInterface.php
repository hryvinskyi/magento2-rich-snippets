<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Library;

use Hryvinskyi\RichSnippets\Api\Data\ConditionTreeInterface;
use Hryvinskyi\RichSnippets\Api\Data\GoogleStatus;
use Hryvinskyi\RichSnippets\Api\Data\LibraryQuestionInterface;
use Hryvinskyi\RichSnippets\Api\Data\LibraryTemplateInterface;

/**
 * Creates library templates and their questions from validated definitions
 */
interface LibraryTemplateFactoryInterface
{
    /**
     * A library template
     *
     * @param string $code
     * @param int $version
     * @param string $group
     * @param string $name
     * @param string $type
     * @param string $icon
     * @param string $description
     * @param string $where
     * @param bool $block
     * @param GoogleStatus $googleStatus
     * @param string $googleShort
     * @param string $googleLong
     * @param list<string> $resultTypes
     * @param list<string> $properties
     * @param list<string> $uses
     * @param list<LibraryQuestionInterface> $questions
     * @param list<string> $pageKinds
     * @param ConditionTreeInterface $conditions
     * @param array<mixed> $template
     * @param string $summary
     * @return LibraryTemplateInterface
     */
    public function template(
        string $code,
        int $version,
        string $group,
        string $name,
        string $type,
        string $icon,
        string $description,
        string $where,
        bool $block,
        GoogleStatus $googleStatus,
        string $googleShort,
        string $googleLong,
        array $resultTypes,
        array $properties,
        array $uses,
        array $questions,
        array $pageKinds,
        ConditionTreeInterface $conditions,
        array $template,
        string $summary = ''
    ): LibraryTemplateInterface;

    /**
     * A question of a library template
     *
     * @param string $code
     * @param string $label
     * @param string $suggester
     * @param string $root
     * @return LibraryQuestionInterface
     * @throws \InvalidArgumentException when the code or the root is not a lower-case identifier
     */
    public function question(string $code, string $label, string $suggester, string $root): LibraryQuestionInterface;
}
