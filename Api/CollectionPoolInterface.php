<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Api;

/**
 * The registered lists, by path
 *
 * @api
 */
interface CollectionPoolInterface
{
    /**
     * The list read by a path
     *
     * @param string $path
     * @return CollectionInterface|null null when no list has this path
     */
    public function get(string $path): ?CollectionInterface;

    /**
     * Every list, in registration order
     *
     * @return list<CollectionInterface>
     */
    public function getCollections(): array;
}
