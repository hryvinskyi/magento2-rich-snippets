<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Api\Data;

/**
 * One property a kind of Google result asks for: a single property path, or a group of paths any one of which will
 * do (`gtin`, `gtin8`, `gtin12`, `gtin13`, `gtin14`)
 *
 * A path is dot-separated through the JSON-LD objects (`offers.price`). A list on the way stands for each of its
 * items: `itemListElement.position` asks every item of `itemListElement` for a position.
 *
 * @api
 */
interface RequirementInterface
{
    /**
     * The paths that satisfy the requirement, the first one standing for the group
     *
     * @return non-empty-list<string>
     */
    public function getPaths(): array;

    /**
     * The path named when the requirement is missing and nothing tells which member a template meant to write
     *
     * @return string
     */
    public function getName(): string;

    /**
     * Whether any one of several paths satisfies the requirement
     *
     * @return bool
     */
    public function isAnyOf(): bool;

    /**
     * The title a flag or finding shows when the requirement is missing (`No offer: Google needs one`)
     *
     * Written in the rule's language and translated where it is shown; empty when the rule gives none, and the
     * reader then names the property path.
     *
     * @return string
     */
    public function getMissingTitle(): string;

    /**
     * Why the property is worth adding, shown when it is the one recommended property a block misses
     *
     * For example `Google can use it to show delivery cost and time with the result.`
     *
     * Written in the rule's language and translated where it is shown; empty when the rule gives none.
     *
     * @return string
     */
    public function getWhy(): string;
}
