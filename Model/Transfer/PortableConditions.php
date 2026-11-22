<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Transfer;

use Hryvinskyi\RichSnippets\Api\ConditionFieldPoolInterface;
use Hryvinskyi\RichSnippets\Api\ConditionOperatorPoolInterface;
use Hryvinskyi\RichSnippets\Api\ConditionTreePrinterInterface;
use Hryvinskyi\RichSnippets\Api\Data\ConditionTreeInterface;
use Hryvinskyi\RichSnippets\Api\PortableConditionValueInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Writes a conditions tree so another shop can resolve it, and resolves a written tree to this shop's values
 *
 * The tree is written in the expression builder's shape, each condition with one more member, `labels`: the label
 * of each of its values, in the order of the values. A field registered in the portable values pool is keyed by a
 * row ID: its value travels with a label that names it anywhere, and is resolved by that label. Any other field's
 * keys are stable everywhere (a product type, a CMS page identifier): the key is kept when the field has that value
 * in one of the shop's store views, and its label is written for the reader only.
 *
 * Resolving changes values only. A condition whose field, attribute or operator this shop does not know, or that is
 * not complete, is left as it is for the parser to judge.
 *
 * @phpstan-type Unresolved array{field: string, attribute: string, value: string, label: string}
 */
class PortableConditions
{
    public const LABELS = 'labels';

    private const TYPE = 'type';
    private const ATTRIBUTE = 'attribute';
    private const OPERATOR = 'operator';
    private const VALUE = 'value';
    private const CONDITIONS = 'conditions';
    private const LIST_SEPARATOR = ',';

    /**
     * @param ConditionFieldPoolInterface $fields
     * @param ConditionOperatorPoolInterface $operators
     * @param ConditionTreePrinterInterface $printer
     * @param StoreManagerInterface $storeManager
     * @param array<string,PortableConditionValueInterface> $portable by condition field code
     */
    public function __construct(
        private readonly ConditionFieldPoolInterface $fields,
        private readonly ConditionOperatorPoolInterface $operators,
        private readonly ConditionTreePrinterInterface $printer,
        private readonly StoreManagerInterface $storeManager,
        private readonly array $portable = []
    ) {
    }

    /**
     * A tree as the export writes it; null for no conditions
     *
     * @param ConditionTreeInterface $tree
     * @return array<mixed>|null
     */
    public function exported(ConditionTreeInterface $tree): ?array
    {
        $written = $this->printer->toArray($tree);

        return $written === [] ? null : $this->labelled($written);
    }

    /**
     * A written tree with this shop's values, and the values this shop does not have
     *
     * @param array<mixed> $tree the decoded tree of the file
     * @return array{tree: array<mixed>, unresolved: list<Unresolved>}
     */
    public function resolved(array $tree): array
    {
        $unresolved = [];
        $resolved = $this->resolvedNode($tree, $unresolved);

        return ['tree' => $resolved, 'unresolved' => $unresolved];
    }

    /**
     * A node of the printed tree with the labels of its conditions' values
     *
     * @param array<mixed> $node
     * @return array<mixed>
     */
    private function labelled(array $node): array
    {
        if (($node[self::TYPE] ?? null) === ConditionTreeInterface::GROUP_TYPE) {
            $children = $node[self::CONDITIONS] ?? [];
            $node[self::CONDITIONS] = array_map(
                fn (mixed $child): mixed => is_array($child) ? $this->labelled($child) : $child,
                is_array($children) ? array_values($children) : []
            );

            return $node;
        }
        $condition = $this->condition($node);
        $node[self::LABELS] = $condition === null ? [] : array_map(
            fn (string $value): string => $this->labelOf($condition['field'], $condition['attribute'], $value) ?? '',
            $this->values($condition['operator'], $condition['value'])
        );

        return $node;
    }

    /**
     * A node of the file's tree with this shop's values and without labels
     *
     * @param array<mixed> $node
     * @param list<Unresolved> $unresolved collects the values this shop does not have
     * @return array<mixed>
     */
    private function resolvedNode(array $node, array &$unresolved): array
    {
        if (($node[self::TYPE] ?? null) === ConditionTreeInterface::GROUP_TYPE) {
            $children = $node[self::CONDITIONS] ?? null;
            if (is_array($children)) {
                $node[self::CONDITIONS] = [];
                foreach ($children as $child) {
                    $node[self::CONDITIONS][] = is_array($child) ? $this->resolvedNode($child, $unresolved) : $child;
                }
            }

            return $node;
        }
        $labels = $node[self::LABELS] ?? [];
        unset($node[self::LABELS]);
        $condition = $this->condition($node);
        if ($condition === null || !$this->fields->has($condition['field'])) {
            return $node;
        }
        $values = $this->values($condition['operator'], $condition['value']);
        $resolved = [];
        foreach ($values as $index => $value) {
            $label = is_array($labels) && is_string($labels[$index] ?? null) ? $labels[$index] : '';
            $key = $this->resolve($condition['field'], $condition['attribute'], $value, $label);
            if ($key === null) {
                $unresolved[] = [
                    'field' => $condition['field'],
                    'attribute' => (string) $condition['attribute'],
                    'value' => $value,
                    'label' => $label,
                ];
            }
            $resolved[] = $key ?? $value;
        }
        if ($resolved !== $values) {
            $node[self::VALUE] = implode(self::LIST_SEPARATOR, $resolved);
        }

        return $node;
    }

    /**
     * The members of a condition node a portable value needs; null when they are not all of the right kind
     *
     * @param array<mixed> $node
     * @return array{field: string, attribute: string|null, operator: string, value: string}|null
     */
    private function condition(array $node): ?array
    {
        $field = $node[self::TYPE] ?? null;
        $attribute = $node[self::ATTRIBUTE] ?? null;
        $operator = $node[self::OPERATOR] ?? null;
        $value = $node[self::VALUE] ?? null;
        if (!is_string($field) || !is_string($operator)
            || ($attribute !== null && !is_string($attribute))
            || !(is_string($value) || is_int($value) || is_float($value))
        ) {
            return null;
        }

        return ['field' => $field, 'attribute' => $attribute, 'operator' => $operator, 'value' => (string) $value];
    }

    /**
     * The values of a condition as its operator reads them; the whole value for an operator this shop lacks
     *
     * @param string $operator
     * @param string $value
     * @return list<string>
     */
    private function values(string $operator, string $value): array
    {
        if (!$this->operators->has($operator)) {
            return $value === '' ? [] : [$value];
        }

        return $this->operators->get($operator)->values($value);
    }

    /**
     * The label that names a value in any shop
     *
     * @param string $field
     * @param string|null $attribute
     * @param string $value
     * @return string|null
     */
    private function labelOf(string $field, ?string $attribute, string $value): ?string
    {
        $portable = $this->portable[$field] ?? null;

        return $portable !== null
            ? $portable->labelOf($value, $attribute)
            : $this->stableLabel($field, $attribute, $value);
    }

    /**
     * This shop's value for a written one
     *
     * @param string $field
     * @param string|null $attribute
     * @param string $value the value in the file
     * @param string $label the label in the file; empty when it carries none
     * @return string|null null when the shop has no such value
     */
    private function resolve(string $field, ?string $attribute, string $value, string $label): ?string
    {
        $portable = $this->portable[$field] ?? null;
        if ($portable !== null) {
            return $portable->resolve($value, $label, $attribute);
        }

        return $this->stableLabel($field, $attribute, $value) !== null ? $value : null;
    }

    /**
     * The label of a stable key in the first store view that has the value, the default one first
     *
     * @param string $field
     * @param string|null $attribute
     * @param string $value
     * @return string|null null when no store view has it, or the field does not offer the attribute
     */
    private function stableLabel(string $field, ?string $attribute, string $value): ?string
    {
        $subject = $this->fields->has($field) ? $this->fields->get($field)->subject($attribute) : null;
        if ($subject === null) {
            return null;
        }
        foreach ($this->storeIds() as $storeId) {
            $label = $subject->valueLabel($value, $storeId);
            if ($label !== null) {
                return $label;
            }
        }

        return null;
    }

    /**
     * The store views to look in, the default one first
     *
     * @return list<int>
     */
    private function storeIds(): array
    {
        $ids = [(int) $this->storeManager->getDefaultStoreView()?->getId()];
        foreach ($this->storeManager->getStores() as $store) {
            $ids[] = (int) $store->getId();
        }

        return array_values(array_unique($ids));
    }
}
