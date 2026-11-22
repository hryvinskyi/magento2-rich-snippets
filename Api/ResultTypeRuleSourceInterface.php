<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Api;

use Hryvinskyi\RichSnippets\Api\Exception\MalformedRuleException;

/**
 * Where one requirement rule's definition comes from: usually a JSON file a module ships
 *
 * A module adds a kind of Google result by adding a source to the rule pool's `sources` argument in its di.xml,
 * under the rule's code. The definition is an object with these keys:
 * - `label` (string), `phrase` (string): the result's name as a label and inside a sentence;
 * - `appliesTo` (list of `{"type": "Product"}` or `{"type": "OnlineStore", "at": "hasMerchantReturnPolicy"}`);
 * - `optional` (bool, default false), `googleStatus` (`ok`, `none` or `gone`, default `ok`);
 * - `required`, `recommended` (lists of entries): an entry is a property path, `{"anyOf": [paths]}`, or an object
 *   with `path` (a property path) or `anyOf` (a list of at least two paths), an optional `missing` (string), the
 *   title a flag or finding shows when the entry is missing (`No image`), and an optional `why` (string), why the
 *   property is worth adding;
 * - `texts`: `requirement` and `met` (strings), `leftOut` and `status` (optional strings).
 * Any other key is refused.
 *
 * @api
 */
interface ResultTypeRuleSourceInterface
{
    /**
     * The definition, decoded
     *
     * @return array<mixed>
     * @throws MalformedRuleException when the definition cannot be read or is not a JSON object
     */
    public function read(): array;
}
