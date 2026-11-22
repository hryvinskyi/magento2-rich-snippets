<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Requirement;

use Hryvinskyi\JsonTemplate\Api\Data\AstNodeKind;
use Hryvinskyi\JsonTemplate\Api\Data\CompiledTemplateInterface;
use Hryvinskyi\RichSnippets\Model\Requirement\Shape\NodeShapeInterface;

/**
 * What a compiled template can write, read from its syntax tree without evaluating it
 *
 * Each node is read by the reader of its kind (an AstNodeKind value, under the node's AstNodeKind::KEY); a kind with
 * no reader of its own is read by the fallback, which lets the node hold anything. Every property the template writes
 * is a possible path, whatever the conditions around it, and so is every path above it; a list's items share the
 * list's path. Below a node that can hold anything every path counts as possible, so the shape never denies a
 * property the template might write.
 *
 * The block's types are the constant strings written at its root `@type`, one name or a list of names.
 *
 * @phpstan-type Shape array{types: list<string>, paths: array<string, true>, open: array<string, true>}
 */
class TemplateShape
{
    private const TYPE = '@type';

    /**
     * @param NodeShapeInterface $fallback reads a node of a kind with no reader of its own
     * @param array<string,NodeShapeInterface> $nodes readers by node kind, keyed by the AstNodeKind values
     */
    public function __construct(
        private readonly NodeShapeInterface $fallback,
        private readonly array $nodes = []
    ) {
    }

    /**
     * The template's types and the paths it can write
     *
     * @param CompiledTemplateInterface $template
     * @return Shape
     */
    public function read(CompiledTemplateInterface $template): array
    {
        $shape = ['types' => [], 'paths' => [], 'open' => []];
        $pending = [[$template->getAst(), '']];
        while ($pending !== []) {
            [$node, $path] = array_shift($pending);
            $kind = $node[AstNodeKind::KEY] ?? null;
            $reading = (is_string($kind) ? $this->nodes[$kind] ?? null : null)?->read($node, $path)
                ?? $this->fallback->read($node, $path);
            foreach ($reading['marks'] as $mark) {
                $this->mark($mark, $shape);
            }
            if ($reading['open']) {
                $shape['open'][$path] = true;
            }
            if ($path === self::TYPE && $reading['text'] !== null && $reading['text'] !== '') {
                $shape['types'][] = $reading['text'];
            }
            array_push($pending, ...$reading['children']);
        }

        return $shape;
    }

    /**
     * Whether the template can write a path
     *
     * @param Shape $shape
     * @param string $path
     * @return bool
     */
    public function has(array $shape, string $path): bool
    {
        if (isset($shape['paths'][$path]) || isset($shape['open'][''])) {
            return true;
        }
        $prefix = '';
        foreach (explode('.', $path) as $segment) {
            $prefix = $prefix === '' ? $segment : $prefix . '.' . $segment;
            if (isset($shape['open'][$prefix])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Mark a path and every path above it as possible
     *
     * @param string $path
     * @param Shape $shape
     * @return void
     */
    private function mark(string $path, array &$shape): void
    {
        $prefix = '';
        foreach ($path === '' ? [] : explode('.', $path) as $segment) {
            $prefix = $prefix === '' ? $segment : $prefix . '.' . $segment;
            $shape['paths'][$prefix] = true;
        }
    }
}
