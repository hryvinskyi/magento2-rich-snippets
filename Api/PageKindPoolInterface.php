<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Api;

/**
 * The kinds of page a snippet can be placed on
 *
 * Every storefront request is a page of exactly one kind: the kind that owns its full action name, or the fallback
 * kind ("every other page") when no kind owns it.
 *
 * @api
 */
interface PageKindPoolInterface
{
    /**
     * The codes of the kinds, in their configured order
     *
     * @return list<string>
     */
    public function getCodes(): array;

    /**
     * Whether a kind has the code
     *
     * @param string $kind
     * @return bool
     */
    public function has(string $kind): bool;

    /**
     * The kind with a code
     *
     * @param string $kind
     * @return PageKindResolverInterface
     * @throws \InvalidArgumentException when no kind has the code
     */
    public function get(string $kind): PageKindResolverInterface;

    /**
     * The code of the kind that owns a full action name; the fallback kind when none does
     *
     * @param string $fullActionName
     * @return string
     */
    public function kindOfAction(string $fullActionName): string;
}
