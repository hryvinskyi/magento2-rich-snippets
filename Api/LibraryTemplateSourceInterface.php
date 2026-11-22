<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Api;

use Hryvinskyi\RichSnippets\Api\Exception\MalformedLibraryTemplateException;

/**
 * Where one library template's definition comes from: usually a JSON file a module ships
 *
 * A module adds a template by adding a source to the library pool's `sources` argument in its di.xml, under the
 * template's code. The definition is an object with these keys:
 * - `version` (int from 1), `group` (a group code the pool declares), `name`, `icon`, `description`, `where`
 *   (strings);
 * - `summary` (string, default empty): what the snippet covers in a few words, for a list of templates offered
 *   together;
 * - `block` (bool, default false): a building block, which has no `placement` and whose template has no `@context`;
 * - `google`: `short` and `long` (strings), and either `results` (requirement rule codes, from whose rules the
 *   status comes) or `status` (`ok`, `none` or `gone`) for a type no rule covers;
 * - `properties` (list of property names), `uses` (list of the library codes of the building blocks the template
 *   `@include`s — every `@include` names one, and each is included);
 * - `placement` (page templates only): `pageKinds` (list of kind codes) and `conditions` (the expression
 *   builder's tree, its leaves on condition fields offered for those kinds of page; default none);
 * - `questions` (default none): list of `{"code", "label", "suggester", "root"}`, `root` defaulting to `product`;
 *   the template holds the placeholder `{{? <code> }}` of each question, and no other;
 * - `template`: the snippet template, a JSON object.
 * Any other key is refused.
 *
 * @api
 */
interface LibraryTemplateSourceInterface
{
    /**
     * The definition, decoded
     *
     * @return array<mixed>
     * @throws MalformedLibraryTemplateException when the definition cannot be read or is not a JSON object
     */
    public function read(): array;
}
