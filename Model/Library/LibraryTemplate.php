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
 * A library template, as its definition states it once validated
 *
 * A building block carries no placement.
 */
class LibraryTemplate implements LibraryTemplateInterface
{
    /**
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
     * @throws \InvalidArgumentException when a building block is given a placement or the version is below 1
     */
    public function __construct(
        private readonly string $code,
        private readonly int $version,
        private readonly string $group,
        private readonly string $name,
        private readonly string $type,
        private readonly string $icon,
        private readonly string $description,
        private readonly string $where,
        private readonly bool $block,
        private readonly GoogleStatus $googleStatus,
        private readonly string $googleShort,
        private readonly string $googleLong,
        private readonly array $resultTypes,
        private readonly array $properties,
        private readonly array $uses,
        private readonly array $questions,
        private readonly array $pageKinds,
        private readonly ConditionTreeInterface $conditions,
        private readonly array $template,
        private readonly string $summary = ''
    ) {
        if ($version < 1) {
            throw new \InvalidArgumentException('A library template version starts at 1.');
        }
        if ($block && ($pageKinds !== [] || !$conditions->isEmpty())) {
            throw new \InvalidArgumentException('A building block has no placement.');
        }
    }

    /**
     * @inheritDoc
     */
    public function getCode(): string
    {
        return $this->code;
    }

    /**
     * @inheritDoc
     */
    public function getVersion(): int
    {
        return $this->version;
    }

    /**
     * @inheritDoc
     */
    public function getGroup(): string
    {
        return $this->group;
    }

    /**
     * @inheritDoc
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @inheritDoc
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * @inheritDoc
     */
    public function getIcon(): string
    {
        return $this->icon;
    }

    /**
     * @inheritDoc
     */
    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * @inheritDoc
     */
    public function getSummary(): string
    {
        return $this->summary;
    }

    /**
     * @inheritDoc
     */
    public function getWhere(): string
    {
        return $this->where;
    }

    /**
     * @inheritDoc
     */
    public function isBlock(): bool
    {
        return $this->block;
    }

    /**
     * @inheritDoc
     */
    public function getGoogleStatus(): GoogleStatus
    {
        return $this->googleStatus;
    }

    /**
     * @inheritDoc
     */
    public function getGoogleShort(): string
    {
        return $this->googleShort;
    }

    /**
     * @inheritDoc
     */
    public function getGoogleLong(): string
    {
        return $this->googleLong;
    }

    /**
     * @inheritDoc
     */
    public function getResultTypes(): array
    {
        return $this->resultTypes;
    }

    /**
     * @inheritDoc
     */
    public function getProperties(): array
    {
        return $this->properties;
    }

    /**
     * @inheritDoc
     */
    public function getUses(): array
    {
        return $this->uses;
    }

    /**
     * @inheritDoc
     */
    public function getQuestions(): array
    {
        return $this->questions;
    }

    /**
     * @inheritDoc
     */
    public function getPageKinds(): array
    {
        return $this->pageKinds;
    }

    /**
     * @inheritDoc
     */
    public function getConditions(): ConditionTreeInterface
    {
        return $this->conditions;
    }

    /**
     * @inheritDoc
     */
    public function getTemplate(): array
    {
        return $this->template;
    }
}
