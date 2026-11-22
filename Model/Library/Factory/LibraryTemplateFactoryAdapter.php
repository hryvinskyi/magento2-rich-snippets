<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Library\Factory;

use Hryvinskyi\RichSnippets\Api\Data\ConditionTreeInterface;
use Hryvinskyi\RichSnippets\Api\Data\GoogleStatus;
use Hryvinskyi\RichSnippets\Api\Data\LibraryQuestionInterface;
use Hryvinskyi\RichSnippets\Api\Data\LibraryQuestionInterfaceFactory;
use Hryvinskyi\RichSnippets\Api\Data\LibraryTemplateInterface;
use Hryvinskyi\RichSnippets\Api\Data\LibraryTemplateInterfaceFactory;
use Hryvinskyi\RichSnippets\Model\Library\LibraryTemplateFactoryInterface;

/**
 * Creates library templates and questions through the factories Magento generates for their interfaces
 */
class LibraryTemplateFactoryAdapter implements LibraryTemplateFactoryInterface
{
    /**
     * @param LibraryTemplateInterfaceFactory $templates
     * @param LibraryQuestionInterfaceFactory $questions
     */
    public function __construct(
        private readonly LibraryTemplateInterfaceFactory $templates,
        private readonly LibraryQuestionInterfaceFactory $questions
    ) {
    }

    /**
     * @inheritDoc
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
    ): LibraryTemplateInterface {
        return $this->templates->create([
            'code' => $code,
            'version' => $version,
            'group' => $group,
            'name' => $name,
            'type' => $type,
            'icon' => $icon,
            'description' => $description,
            'where' => $where,
            'block' => $block,
            'googleStatus' => $googleStatus,
            'googleShort' => $googleShort,
            'googleLong' => $googleLong,
            'resultTypes' => $resultTypes,
            'properties' => $properties,
            'uses' => $uses,
            'questions' => $questions,
            'pageKinds' => $pageKinds,
            'conditions' => $conditions,
            'template' => $template,
            'summary' => $summary,
        ]);
    }

    /**
     * @inheritDoc
     */
    public function question(string $code, string $label, string $suggester, string $root): LibraryQuestionInterface
    {
        return $this->questions->create([
            'code' => $code,
            'label' => $label,
            'suggester' => $suggester,
            'root' => $root,
        ]);
    }
}
