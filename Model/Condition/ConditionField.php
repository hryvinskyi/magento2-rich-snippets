<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Condition;

use Hryvinskyi\RichSnippets\Api\ConditionFieldInterface;
use Hryvinskyi\RichSnippets\Api\ConditionSubjectInterface;
use Hryvinskyi\RichSnippets\Api\ConditionValueSqlInterface;
use Hryvinskyi\RichSnippets\Api\Data\PageEntityInterface;

/**
 * A condition field that reads one subject of the pages of one kind, its values coming from a value source
 *
 * The field is its own subject: a condition on it names no attribute. Its operators are the builder package's set for
 * its input type (`select`: is, is not, is one of, is not one of), or the membership pair for a tree of options. A page
 * of another kind, or one that shows no entity, has no value.
 */
class ConditionField implements ConditionFieldInterface, ConditionSubjectInterface
{
    private const TREE = 'tree';
    private const VALUE_TYPE = 'select';

    /**
     * @param ConditionValueSourceInterface $source
     * @param OperatorDescriptors $operators
     * @param string $pageKind the code of the kind of page the field is offered for
     * @param string $label the field's label, in English; translated when it is read
     * @param string $phrase the words naming the field inside a sentence, in English; translated when it is read
     * @param string $inputType `select` for a list of options, `tree` for a tree of them
     * @param bool $namesOwnValue false when the field's value is the page itself
     */
    public function __construct(
        private readonly ConditionValueSourceInterface $source,
        private readonly OperatorDescriptors $operators,
        private readonly string $pageKind,
        private readonly string $label,
        private readonly string $phrase,
        private readonly string $inputType = 'select',
        private readonly bool $namesOwnValue = true
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getPageKind(): string
    {
        return $this->pageKind;
    }

    /**
     * @inheritDoc
     */
    public function getLabel(): string
    {
        return (string) __($this->label);
    }

    /**
     * @inheritDoc
     */
    public function subject(?string $attribute): ?ConditionSubjectInterface
    {
        return $attribute === null ? $this : null;
    }

    /**
     * @inheritDoc
     */
    public function builderField(string $type, int $storeId): array
    {
        return [
            'type' => $type,
            'label' => $this->getLabel(),
            'attribute' => null,
            'valueType' => self::VALUE_TYPE,
            'operators' => $this->operators->describe($this->getOperators()),
            'options' => $this->source->options($storeId),
        ];
    }

    /**
     * @inheritDoc
     */
    public function options(?string $attribute, int $storeId): ?array
    {
        return $attribute === null ? $this->source->options($storeId) : null;
    }

    /**
     * @inheritDoc
     */
    public function suggestions(?string $attribute, string $query): array
    {
        return [];
    }

    /**
     * @inheritDoc
     */
    public function getPhrase(): string
    {
        return (string) __($this->phrase);
    }

    /**
     * @inheritDoc
     */
    public function getOperators(): array
    {
        return $this->inputType === self::TREE
            ? $this->operators->membershipCodes()
            : $this->operators->codesFor($this->inputType) ?? [];
    }

    /**
     * @inheritDoc
     */
    public function getUnit(): string
    {
        return '';
    }

    /**
     * @inheritDoc
     */
    public function namesOwnValue(): bool
    {
        return $this->namesOwnValue;
    }

    /**
     * @inheritDoc
     */
    public function pageValues(PageEntityInterface $page): array
    {
        $entityId = $page->getEntityId();
        if ($entityId === null || $page->getKind() !== $this->pageKind) {
            return [];
        }

        return $this->source->entityValues($entityId, $page->getStoreId());
    }

    /**
     * @inheritDoc
     */
    public function valueLabel(string $value, int $storeId): ?string
    {
        return $this->source->label($value, $storeId);
    }

    /**
     * @inheritDoc
     */
    public function valueSql(string $entityIdExpression, int $storeId): ConditionValueSqlInterface
    {
        return $this->source->valueSql($entityIdExpression, $storeId);
    }

    /**
     * @inheritDoc
     */
    public function getVariablePaths(): array
    {
        return $this->source->variablePaths();
    }
}
