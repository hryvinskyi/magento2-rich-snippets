<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Transfer;

use Hryvinskyi\RichSnippets\Api\Data\ImportChoice;
use Hryvinskyi\RichSnippets\Api\Data\ImportEntryInterface;
use Hryvinskyi\RichSnippets\Api\Data\ImportProblemInterface;
use Hryvinskyi\RichSnippets\Api\Data\ImportStatus;

/**
 * A snippet of an import file as the import found it
 */
class ImportEntry implements ImportEntryInterface
{
    /**
     * @param string $code
     * @param string $name
     * @param string $type
     * @param bool $block
     * @param ImportStatus $status
     * @param int|null $existingId
     * @param string|null $changedHere
     * @param string|null $changedInFile
     * @param list<ImportChoice> $choices
     * @param list<ImportProblemInterface> $problems
     * @param list<string> $pageKinds
     * @param string $conditionSummary
     */
    public function __construct(
        private readonly string $code,
        private readonly string $name,
        private readonly string $type,
        private readonly bool $block,
        private readonly ImportStatus $status,
        private readonly ?int $existingId,
        private readonly ?string $changedHere,
        private readonly ?string $changedInFile,
        private readonly array $choices,
        private readonly array $problems,
        private readonly array $pageKinds,
        private readonly string $conditionSummary
    ) {
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
    public function isBlock(): bool
    {
        return $this->block;
    }

    /**
     * @inheritDoc
     */
    public function getStatus(): ImportStatus
    {
        return $this->status;
    }

    /**
     * @inheritDoc
     */
    public function getExistingId(): ?int
    {
        return $this->existingId;
    }

    /**
     * @inheritDoc
     */
    public function getChangedHere(): ?string
    {
        return $this->changedHere;
    }

    /**
     * @inheritDoc
     */
    public function getChangedInFile(): ?string
    {
        return $this->changedInFile;
    }

    /**
     * @inheritDoc
     */
    public function getChoices(): array
    {
        return $this->choices;
    }

    /**
     * @inheritDoc
     */
    public function getProblems(): array
    {
        return $this->problems;
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
    public function getConditionSummary(): string
    {
        return $this->conditionSummary;
    }
}
