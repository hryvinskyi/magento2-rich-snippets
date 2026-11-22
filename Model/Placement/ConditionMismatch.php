<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Placement;

use Hryvinskyi\RichSnippets\Api\Data\ConditionLeafInterface;
use Hryvinskyi\RichSnippets\Api\Data\ConditionMismatchInterface;

/**
 * A condition a page does not meet, with the words the explanation is written from
 */
class ConditionMismatch implements ConditionMismatchInterface
{
    /**
     * @param ConditionLeafInterface $condition
     * @param string $requirement
     * @param string $fieldLabel
     * @param string $operatorLabel
     * @param list<string> $valueLabels
     * @param string|null $pageValue
     */
    public function __construct(
        private readonly ConditionLeafInterface $condition,
        private readonly string $requirement,
        private readonly string $fieldLabel,
        private readonly string $operatorLabel,
        private readonly array $valueLabels,
        private readonly ?string $pageValue
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getCondition(): ConditionLeafInterface
    {
        return $this->condition;
    }

    /**
     * @inheritDoc
     */
    public function getRequirement(): string
    {
        return $this->requirement;
    }

    /**
     * @inheritDoc
     */
    public function getFieldLabel(): string
    {
        return $this->fieldLabel;
    }

    /**
     * @inheritDoc
     */
    public function getOperatorLabel(): string
    {
        return $this->operatorLabel;
    }

    /**
     * @inheritDoc
     */
    public function getValueLabels(): array
    {
        return $this->valueLabels;
    }

    /**
     * @inheritDoc
     */
    public function getPageValue(): ?string
    {
        return $this->pageValue;
    }
}
