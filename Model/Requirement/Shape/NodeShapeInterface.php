<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Requirement\Shape;

/**
 * What one kind of syntax tree node can write, without evaluating it
 *
 * The node kinds and the keys of each are the ones \Hryvinskyi\JsonTemplate\Api\Data\AstNodeKind publishes.
 * A reader says which paths the node itself writes, whether everything below its path may be written too, and
 * which child nodes stand at which paths; the template reader walks the children.
 *
 * @phpstan-type NodeReading array{
 *     marks: list<string>,
 *     open: bool,
 *     children: list<array{0: array<mixed>, 1: string}>,
 *     text: string|null
 * }
 */
interface NodeShapeInterface
{
    /**
     * What a node writes at a path
     *
     * @param array<mixed> $node
     * @param string $path dot-separated, empty for the root
     * @return NodeReading marks: the paths written; open: whether anything below the path may be written;
     *     children: each child node with its path; text: the string a node writes as a constant, else null
     */
    public function read(array $node, string $path): array;
}
