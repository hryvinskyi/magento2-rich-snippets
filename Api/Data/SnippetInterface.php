<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Api\Data;

/**
 * One JSON-LD block a shop writes, as every reader sees it: its code, name, schema.org type, template and placement
 *
 * Read-only: a snippet changes only through the module's own rules, so a building block can never be switched on or
 * placed through a setter. The template is the source of truth; the type is the first schema.org type of its root.
 *
 * @api
 */
interface SnippetInterface
{
    /**
     * The grammar of a code: a lowercase letter, then 1 to 63 lowercase letters, digits or hyphens
     *
     * The alphabet has no character that can close an HTML comment or attribute.
     */
    public const CODE_PATTERN = '/\A[a-z][a-z0-9-]{1,63}\z/';

    /**
     * The grammar of a schema.org type: an uppercase letter, then letters and digits
     */
    public const TYPE_PATTERN = '/\A[A-Z][A-Za-z0-9]*\z/';

    /**
     * The store id that stands for every store view
     */
    public const ALL_STORES = 0;

    /**
     * The snippet's ID; null until it is saved
     *
     * @return int|null
     */
    public function getId(): ?int;

    /**
     * The stable code `@include` and import match by; it never changes
     *
     * @return string
     */
    public function getCode(): string;

    /**
     * The name the admin shows
     *
     * @return string
     */
    public function getName(): string;

    /**
     * The first schema.org type of the template's root; empty when the template has none
     *
     * @return string
     */
    public function getType(): string;

    /**
     * Whether the snippet is a building block: output only inside the snippets that include it
     *
     * @return bool
     */
    public function isBlock(): bool;

    /**
     * Whether the snippet is switched on; a building block never is
     *
     * @return bool
     */
    public function isActive(): bool;

    /**
     * The kinds of page the snippet is output on; empty for a building block, and for a page snippet output nowhere
     *
     * @return list<string>
     */
    public function getPageKinds(): array;

    /**
     * The conditions under "Only when"; an empty tree means every page of the kinds
     *
     * @return ConditionTreeInterface
     */
    public function getConditions(): ConditionTreeInterface;

    /**
     * The store views the snippet is output in
     *
     * `[0]` is every store view; the list is empty when every store view it was set for has been deleted.
     *
     * @return list<int>
     */
    public function getStoreIds(): array;

    /**
     * The JSON template
     *
     * @return string
     */
    public function getTemplate(): string;

    /**
     * The library template the snippet was added from; null for one written in the shop
     *
     * @return OriginInterface|null
     */
    public function getOrigin(): ?OriginInterface;

    /**
     * The output order on a page, from 0; ties break by ID
     *
     * @return int
     */
    public function getSortOrder(): int;

    /**
     * When the snippet was saved last, as the database stores it (`Y-m-d H:i:s`, UTC); null until it is saved
     *
     * @return string|null
     */
    public function getUpdatedAt(): ?string;

    /**
     * The user name of the admin who saved the snippet last; empty for a save without one
     *
     * @return string
     */
    public function getUpdatedBy(): string;
}
