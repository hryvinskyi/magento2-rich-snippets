<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Api\Data;

/**
 * One thing wrong with a draft, addressed to the part of it that is wrong
 *
 * A problem with the template carries the JSON path of the value it concerns (`$.offers.price`) and, for an error
 * inside a string, where in the string it is. A problem with a condition carries the condition's position (`$[1]`);
 * every other problem concerns its part as a whole (`$`). The code is the engine's for a template that does not
 * compile (`unknown-filter`), a snippet rule's otherwise (`type_missing`), or `no_page_kinds` for a page snippet
 * output nowhere. Problems carry no wording: the admin words them.
 *
 * @api
 */
interface DraftProblemInterface
{
    /** The template */
    public const PART_TEMPLATE = 'template';

    /** The name */
    public const PART_NAME = 'name';

    /** The schema.org type at the template's root */
    public const PART_TYPE = 'type';

    /** The kinds of page */
    public const PART_PAGE_KINDS = 'pageKinds';

    /** The conditions */
    public const PART_CONDITIONS = 'conditions';

    /** The store views */
    public const PART_STORES = 'storeIds';

    /** The snippet as a whole: its code, which is made from its name, or a rule of a building block */
    public const PART_SNIPPET = 'snippet';

    /** The code of a page snippet with no kind of page: allowed, but it is output nowhere */
    public const NO_PAGE_KINDS = 'no_page_kinds';

    /**
     * The part of the draft that is wrong: one of the PART_* constants
     *
     * @return string
     */
    public function getPart(): string;

    /**
     * Where inside the part: a JSON path in the template, `$[n]` for the n-th condition from 0, `$` for the part
     *
     * @return string
     */
    public function getPath(): string;

    /**
     * What is wrong
     *
     * @return string
     */
    public function getCode(): string;

    /**
     * The values the code refers to, by name
     *
     * @return array<string,scalar|null>
     */
    public function getParams(): array;

    /**
     * Where in the string value a template error is, in Unicode code points; null for the value as a whole
     *
     * @return int|null
     */
    public function getOffset(): ?int;

    /**
     * Whether the problem stops the draft from being saved
     *
     * A page snippet with no kind of page is the one problem that does not: it is saved and output nowhere.
     *
     * @return bool
     */
    public function isBlocking(): bool;
}
