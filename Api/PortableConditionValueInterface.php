<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Api;

/**
 * Carries the values of a condition field keyed by a row ID (an attribute set, a category, an attribute option) from
 * one shop to another
 *
 * A row ID means nothing in another shop, so an export writes, beside it, a label that names the same thing in any
 * shop, and an import resolves the label back to the ID it has there. A field whose keys are stable everywhere (a
 * product type, a CMS page identifier) needs none. Registered in the portable values pool (di.xml) under the
 * condition field's code; another module adds one there for a field of its own. A field that reads several
 * attributes is told which one the condition reads; a field of one attribute is told none.
 *
 * @api
 */
interface PortableConditionValueInterface
{
    /**
     * The label that names a value in any shop
     *
     * @param string $key the value's key in this shop
     * @param string|null $attribute the attribute the condition reads; null for a field of one attribute
     * @return string|null null when no value of this shop has the key
     */
    public function labelOf(string $key, ?string $attribute): ?string;

    /**
     * This shop's key for a value exported with a key and a label
     *
     * The key is taken as it is only when it names a value with the same label here; otherwise the value with the
     * label is looked for. A label two values share here resolves to nothing, unless the key is one of them.
     *
     * @param string $key the key in the shop that exported it
     * @param string $label
     * @param string|null $attribute the attribute the condition reads; null for a field of one attribute
     * @return string|null null when nothing here has the label
     */
    public function resolve(string $key, string $label, ?string $attribute): ?string;
}
