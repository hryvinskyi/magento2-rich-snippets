<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Api\Data;

/**
 * A snippet as the editor holds it before it is saved: the values the admin can change, nothing derived
 *
 * The template is the source of truth: the type is read from its root's `@type`. A draft of a stored snippet names
 * it by its ID, and keeps that snippet's code; a new draft has neither until it is saved.
 *
 * @api
 */
interface DraftSnippetInterface
{
    /**
     * The stored snippet the draft edits; null for a new snippet
     *
     * @return int|null
     */
    public function getId(): ?int;

    /**
     * The name the admin shows
     *
     * @return string
     */
    public function getName(): string;

    /**
     * The JSON template, as typed
     *
     * @return string
     */
    public function getTemplate(): string;

    /**
     * Whether the snippet is a building block: output only inside the snippets that include it
     *
     * @return bool
     */
    public function isBlock(): bool;

    /**
     * Whether the snippet is to be switched on; ignored for a building block
     *
     * @return bool
     */
    public function isActive(): bool;

    /**
     * The kinds of page the snippet is to be output on; ignored for a building block
     *
     * @return list<string>
     */
    public function getPageKinds(): array;

    /**
     * The conditions a page must meet, as the expression builder's text; empty for none
     *
     * Ignored for a building block. Whether the text reads as conditions is the snippet rules' to say when the draft
     * is checked.
     *
     * @return string
     */
    public function getConditions(): string;

    /**
     * The store views the snippet is to be output in; `[0]` is every store view
     *
     * @return list<int>
     */
    public function getStoreIds(): array;
}
