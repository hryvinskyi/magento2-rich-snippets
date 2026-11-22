<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Api\Data;

/**
 * One row of the snippet list: the snippet with what its row says about it
 *
 * @api
 */
interface SnippetRowInterface
{
    /**
     * The snippet
     *
     * @return SnippetInterface
     */
    public function getSnippet(): SnippetInterface;

    /**
     * The conditions in words, translated ("Product type is not Configurable"); empty when it has none
     *
     * @return string
     */
    public function getConditionSummary(): string;

    /**
     * The names of the store views it is output in; empty when it is output in every store view
     *
     * @return list<string>
     */
    public function getStoreNames(): array;

    /**
     * How many properties the template writes: those of its root and of the objects directly inside the root
     *
     * @return int
     */
    public function getPropertyCount(): int;

    /**
     * The snippets its template includes, in the order the template names them
     *
     * @return array<string,string|null> the name by code; null for a code no snippet has
     */
    public function getUses(): array;

    /**
     * The snippets whose template includes it, in code order
     *
     * @return array<string,string> the name by code
     */
    public function getUsedIn(): array;

    /**
     * Its flags, in the order the providers gave them
     *
     * @return list<SnippetFlagInterface>
     */
    public function getFlags(): array;
}
