<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Selection;

use Hryvinskyi\RichSnippets\Api\Data\SnippetInterface;

/**
 * The switched-on page snippets each kind of page of a store view gets before conditions are tested
 */
interface SelectionIndexInterface
{
    /**
     * The switched-on page snippets set for a kind of page in a store view, by sort order, then ID
     *
     * A snippet set for every store view is set for each of them.
     *
     * @param string $kind
     * @param int $storeId
     * @return list<SnippetInterface>
     */
    public function snippetsFor(string $kind, int $storeId): array;
}
