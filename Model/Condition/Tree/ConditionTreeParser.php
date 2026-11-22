<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Condition\Tree;

use Hryvinskyi\RichSnippets\Api\ConditionFieldPoolInterface;
use Hryvinskyi\RichSnippets\Api\ConditionTreeParserInterface;
use Hryvinskyi\RichSnippets\Api\Data\ConditionAggregator;
use Hryvinskyi\RichSnippets\Api\Data\ConditionLeafInterface;
use Hryvinskyi\RichSnippets\Api\Data\ConditionNodeInterface;
use Hryvinskyi\RichSnippets\Api\Data\ConditionTreeInterface;
use Hryvinskyi\RichSnippets\Api\Exception\SnippetRuleException;
use Hryvinskyi\RichSnippets\Api\Exception\SnippetRuleReason;
use Hryvinskyi\UiFormElements\Api\ConditionTreeValidatorInterface;

/**
 * Reads the builder's conditions, refusing whatever the builder cannot have written
 *
 * The walk names the first fault it meets (an unknown field, an attribute the field does not read, an operator it
 * does not offer, groups nested too deep); the builder's own validator then gates the whole tree on its condition
 * types and node depth, the same check every page that hydrates the builder's value runs. An aggregator other than
 * `any` reads as `all`, as the builder reads it. A list value stored as an array reads as its values joined by
 * commas, the builder's own form.
 *
 * Restoring a stored text keeps the structural checks and leaves out those against the shop's fields, attributes
 * and operators, which may have changed since the text was written.
 */
class ConditionTreeParser implements ConditionTreeParserInterface
{
    private const DECODE_DEPTH = 64;
    private const LIST_SEPARATOR = ',';

    /**
     * @param ConditionTreeValidatorInterface $validator
     * @param ConditionFieldPoolInterface $fields
     * @param ConditionTreeFactoryInterface $nodes
     */
    public function __construct(
        private readonly ConditionTreeValidatorInterface $validator,
        private readonly ConditionFieldPoolInterface $fields,
        private readonly ConditionTreeFactoryInterface $nodes
    ) {
    }

    /**
     * @inheritDoc
     */
    public function parse(string $json): ConditionTreeInterface
    {
        return $this->parseDecoded($this->decoded($json));
    }

    /**
     * @inheritDoc
     */
    public function restore(string $json): ConditionTreeInterface
    {
        $tree = $this->decoded($json);
        if ($tree === []) {
            return $this->nodes->tree();
        }
        $this->assertGroup($tree);

        return $this->nodes->tree($this->aggregator($tree), $this->children($tree, 1, false));
    }

    /**
     * The decoded text; empty for an empty text
     *
     * @param string $json
     * @return array<mixed>
     * @throws SnippetRuleException
     */
    private function decoded(string $json): array
    {
        if (trim($json) === '') {
            return [];
        }
        try {
            $decoded = json_decode($json, true, self::DECODE_DEPTH, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new SnippetRuleException(SnippetRuleReason::ConditionInvalid, [], $e);
        }
        if (!is_array($decoded)) {
            throw new SnippetRuleException(SnippetRuleReason::ConditionInvalid);
        }

        return $decoded;
    }

    /**
     * @inheritDoc
     */
    public function parseDecoded(array $tree): ConditionTreeInterface
    {
        if ($tree === []) {
            return $this->nodes->tree();
        }
        $this->assertGroup($tree);
        $parsed = $this->nodes->tree($this->aggregator($tree), $this->children($tree, 1, true));
        $allowed = array_merge([ConditionTreeInterface::GROUP_TYPE], $this->fields->getCodes());
        if (!$this->validator->isValid($tree, $allowed, ConditionTreeValidatorInterface::DEFAULT_MAX_DEPTH)) {
            throw new SnippetRuleException(SnippetRuleReason::ConditionInvalid);
        }

        return $parsed;
    }

    /**
     * Refuse a root that is not a group
     *
     * @param array<mixed> $tree
     * @return void
     * @throws SnippetRuleException
     */
    private function assertGroup(array $tree): void
    {
        if (($tree['type'] ?? null) !== ConditionTreeInterface::GROUP_TYPE) {
            throw new SnippetRuleException(SnippetRuleReason::ConditionInvalid);
        }
    }

    /**
     * The nodes a group holds
     *
     * @param array<mixed> $group
     * @param int $depth the group's level, the outermost being 1
     * @param bool $checked whether each condition is checked against the shop's fields, attributes and operators
     * @return list<ConditionNodeInterface>
     * @throws SnippetRuleException
     */
    private function children(array $group, int $depth, bool $checked): array
    {
        $entries = $group['conditions'] ?? [];
        if (!is_array($entries) || !array_is_list($entries)) {
            throw new SnippetRuleException(SnippetRuleReason::ConditionInvalid);
        }
        $children = [];
        foreach ($entries as $entry) {
            if (!is_array($entry) || !is_string($entry['type'] ?? null)) {
                throw new SnippetRuleException(SnippetRuleReason::ConditionInvalid);
            }
            $children[] = $entry['type'] === ConditionTreeInterface::GROUP_TYPE
                ? $this->group($entry, $depth + 1, $checked)
                : $this->leaf($entry['type'], $entry, $checked);
        }

        return $children;
    }

    /**
     * A nested group, refused when it nests deeper than the builder allows
     *
     * @param array<mixed> $entry
     * @param int $depth
     * @param bool $checked
     * @return ConditionNodeInterface
     * @throws SnippetRuleException
     */
    private function group(array $entry, int $depth, bool $checked): ConditionNodeInterface
    {
        if ($depth > self::GROUP_DEPTH) {
            throw new SnippetRuleException(
                SnippetRuleReason::ConditionsTooDeep,
                ['depth' => (string) self::GROUP_DEPTH]
            );
        }

        return $this->nodes->group($this->aggregator($entry), $this->children($entry, $depth, $checked));
    }

    /**
     * A condition; when checked, refused if its field, attribute or operator is not one the pools offer
     *
     * @param string $field
     * @param array<mixed> $entry
     * @param bool $checked
     * @return ConditionLeafInterface
     * @throws SnippetRuleException
     */
    private function leaf(string $field, array $entry, bool $checked): ConditionLeafInterface
    {
        $attribute = $entry['attribute'] ?? null;
        $operator = $entry['operator'] ?? null;
        if ($checked) {
            $this->assertOffered($field, $attribute, $operator);
        }
        if (($attribute !== null && !is_string($attribute)) || !is_string($operator)) {
            throw new SnippetRuleException(SnippetRuleReason::ConditionInvalid);
        }
        $processed = $entry['is_value_processed'] ?? null;

        return $this->nodes->leaf(
            $field,
            $attribute,
            $operator,
            $this->value($entry['value'] ?? null),
            is_bool($processed) ? $processed : null
        );
    }

    /**
     * Refuse a condition whose field, attribute or operator is not one the pools offer
     *
     * @param string $field
     * @param mixed $attribute
     * @param mixed $operator
     * @return void
     * @throws SnippetRuleException
     */
    private function assertOffered(string $field, mixed $attribute, mixed $operator): void
    {
        if (!$this->fields->has($field)) {
            throw new SnippetRuleException(SnippetRuleReason::ConditionFieldUnknown, ['field' => $field]);
        }
        if ($attribute !== null && !is_string($attribute)) {
            throw new SnippetRuleException(SnippetRuleReason::ConditionInvalid);
        }
        $subject = $this->fields->get($field)->subject($attribute);
        if ($subject === null) {
            throw new SnippetRuleException(
                SnippetRuleReason::ConditionAttributeUnknown,
                ['field' => $field, 'attribute' => $attribute ?? '']
            );
        }
        if (!is_string($operator) || !in_array($operator, $subject->getOperators(), true)) {
            throw new SnippetRuleException(
                SnippetRuleReason::ConditionOperatorUnknown,
                ['field' => $field, 'operator' => is_string($operator) ? $operator : '']
            );
        }
    }

    /**
     * A stored value: text or a number as it is, a list joined by commas, nothing as empty text
     *
     * @param mixed $value
     * @return string|int|float
     * @throws SnippetRuleException when the value is of another type
     */
    private function value(mixed $value): string|int|float
    {
        if ($value === null) {
            return '';
        }
        if (is_string($value) || is_int($value) || is_float($value)) {
            return $value;
        }
        if (!is_array($value)) {
            throw new SnippetRuleException(SnippetRuleReason::ConditionInvalid);
        }
        $listed = [];
        foreach ($value as $item) {
            if (!is_string($item) && !is_int($item) && !is_float($item)) {
                throw new SnippetRuleException(SnippetRuleReason::ConditionInvalid);
            }
            $listed[] = (string) $item;
        }

        return implode(self::LIST_SEPARATOR, $listed);
    }

    /**
     * A group's aggregator, `all` for anything but `any`
     *
     * @param array<mixed> $group
     * @return ConditionAggregator
     */
    private function aggregator(array $group): ConditionAggregator
    {
        return ($group['aggregator'] ?? null) === ConditionAggregator::Any->value
            ? ConditionAggregator::Any
            : ConditionAggregator::All;
    }
}
