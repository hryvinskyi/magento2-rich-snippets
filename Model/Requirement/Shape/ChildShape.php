<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Requirement\Shape;

/**
 * A node whose children stand at its own path: a list's items, or the body of an object with directives (whose
 * items, when it repeats, share the list's path)
 */
class ChildShape implements NodeShapeInterface
{
    /**
     * @param string $key the node key holding the children
     * @param bool $many whether that key holds a list of nodes rather than one node
     */
    public function __construct(
        private readonly string $key,
        private readonly bool $many
    ) {
    }

    /**
     * @inheritDoc
     */
    public function read(array $node, string $path): array
    {
        $held = $node[$this->key] ?? null;
        $nodes = $this->many && is_array($held) ? $held : [$held];
        $children = [];
        foreach ($nodes as $child) {
            $children[] = [is_array($child) ? $child : [], $path];
        }

        return ['marks' => [], 'open' => false, 'children' => $children, 'text' => null];
    }
}
