<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Requirement\Shape;

/**
 * An object: each entry's node stands one property further down
 */
class ObjectShape implements NodeShapeInterface
{
    /**
     * @inheritDoc
     */
    public function read(array $node, string $path): array
    {
        $children = [];
        foreach (is_array($node['entries'] ?? null) ? $node['entries'] : [] as $entry) {
            if (is_array($entry) && is_string($entry[0] ?? null)) {
                $child = $entry[1] ?? null;
                $children[] = [is_array($child) ? $child : [], $path === '' ? $entry[0] : $path . '.' . $entry[0]];
            }
        }

        return ['marks' => [$path], 'open' => false, 'children' => $children, 'text' => null];
    }
}
