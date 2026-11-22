<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Snippet;

/**
 * Reads stored snippets
 */
interface SnippetReaderInterface
{
    /**
     * The stored snippet with an ID
     *
     * @param int $id
     * @return SnippetRootInterface|null
     */
    public function byId(int $id): ?SnippetRootInterface;

    /**
     * The stored snippet with a code
     *
     * @param string $code
     * @return SnippetRootInterface|null
     */
    public function byCode(string $code): ?SnippetRootInterface;

    /**
     * The stored snippets with any of the codes, in code order
     *
     * @param list<string> $codes
     * @return list<SnippetRootInterface>
     */
    public function byCodes(array $codes): array;

    /**
     * The stored snippets that meet every criterion given, by sort order, then ID
     *
     * @param int|null $storeId only snippets output in the store view: those listing it or every store view
     * @param string|null $pageKind only snippets output on the kind of page
     * @param bool|null $active only snippets switched on (true) or off (false)
     * @param bool|null $block only building blocks (true) or page snippets (false)
     * @return list<SnippetRootInterface>
     */
    public function matching(?int $storeId, ?string $pageKind, ?bool $active, ?bool $block): array;

    /**
     * The stored codes that start with a prefix
     *
     * @param string $prefix
     * @return list<string>
     */
    public function codesStartingWith(string $prefix): array;
}
