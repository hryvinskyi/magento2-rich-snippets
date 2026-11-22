<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Api;

/**
 * A field the expression builder offers under "Only when" for one kind of page: a product attribute, the attribute
 * set, the category, the product type, the CMS page, the category page
 *
 * A field reads one subject, or, like the product attribute field, one of many named by the condition's attribute.
 * It is registered in the condition field pool under its code, which is the condition type the builder stores;
 * another module adds a field by adding an item there.
 *
 * @api
 */
interface ConditionFieldInterface
{
    /**
     * The code of the kind of page the field is offered for
     *
     * @return string
     */
    public function getPageKind(): string;

    /**
     * The label the builder's Field list shows, translated
     *
     * @return string
     */
    public function getLabel(): string;

    /**
     * What a condition on the field reads of a page
     *
     * @param string|null $attribute the condition's attribute; null for a field that reads one subject
     * @return ConditionSubjectInterface|null null when the field offers no such attribute, or reads one and none is
     *     named
     */
    public function subject(?string $attribute): ?ConditionSubjectInterface;

    /**
     * The field as the expression builder's `fields` entry describes it: `type`, `label`, `attribute`, `valueType`,
     * `operators`, and `options` or the picker's `childType` and `attributes`
     *
     * @param string $type the field's code, written as the entry's condition type
     * @param int $storeId the store view whose labels the options carry
     * @return array<string,mixed>
     */
    public function builderField(string $type, int $storeId): array;

    /**
     * The values a condition on the field can pick, as the builder's options
     *
     * Each is `{value, label}`, with `level` and `parent` as well in a tree.
     *
     * @param string|null $attribute
     * @param int $storeId
     * @return list<array<string,string|int>>|null null when the field offers no such attribute
     */
    public function options(?string $attribute, int $storeId): ?array;

    /**
     * Values of an attribute matching a query, for the builder's type-ahead `{value, label}`
     *
     * @param string|null $attribute
     * @param string $query
     * @return list<array{value: string,label: string}> none when the field suggests nothing for the attribute
     */
    public function suggestions(?string $attribute, string $query): array;
}
