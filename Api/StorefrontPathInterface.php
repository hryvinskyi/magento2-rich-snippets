<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Api;

/**
 * What counts as a path of a storefront: the only part of a storefront address a person may give
 *
 * A path starts with `/` and may carry a query. It carries no white space, control character or backslash, and no
 * parameter that picks a store view: the store view is chosen apart from the path, and a store parameter would read
 * a page cache entry no visitor gets. A fragment is dropped, and so are extra slashes at the start, so the path can
 * never be read as a host.
 *
 * @api
 */
interface StorefrontPathInterface
{
    /**
     * The path as the storefront is asked for it
     *
     * @param string $path
     * @return string starting with `/`; `/` for an empty path
     * @throws \InvalidArgumentException when it is not a storefront path
     */
    public function normalize(string $path): string;
}
