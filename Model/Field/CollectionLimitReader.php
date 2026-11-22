<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Field;

use Hryvinskyi\JsonTemplate\Api\Data\AstNodeKind;
use Hryvinskyi\RichSnippets\Api\CollectionLimitsInterface;

/**
 * Finds the list limits in the compiled templates' syntax trees
 *
 * The trees are the plain arrays the engine publishes for its compiled templates, their kinds on AstNodeKind: a
 * `directive` node holds its directives' arguments (`each`, `limit`, `if`) and a `body`; a `path` node holds its
 * `segments`; a `literal` holds JSON written in the template, which is never read as a node.
 */
class CollectionLimitReader implements CollectionLimitsInterface
{
    /**
     * @inheritDoc
     */
    public function limits(array $templates): array
    {
        $limited = [];
        $whole = [];
        $reads = [];
        foreach ($templates as $template) {
            $this->walk($template->getAst(), $limited, $whole, $reads);
        }
        foreach (array_keys($reads) as $read) {
            foreach (array_keys($limited) as $list) {
                if ($read === $list || str_starts_with($read, $list . '.')) {
                    $whole[$list] = true;
                }
            }
        }

        return array_diff_key($limited, $whole);
    }

    /**
     * Walk a node, noting each limited `@each`, each list read whole and every other path read
     *
     * @param mixed $node
     * @param array<string,int> $limited
     * @param array<string,true> $whole
     * @param array<string,true> $reads
     * @return void
     */
    private function walk(mixed $node, array &$limited, array &$whole, array &$reads): void
    {
        if (!is_array($node)) {
            return;
        }
        $kind = $node[AstNodeKind::KEY] ?? null;
        if ($kind === AstNodeKind::LITERAL) {
            return;
        }
        if ($kind === AstNodeKind::PATH) {
            $path = $this->path($node);
            if ($path !== null) {
                $reads[$path] = true;
            }

            return;
        }
        if ($kind === AstNodeKind::DIRECTIVE && is_array($node['arguments'] ?? null)) {
            $arguments = $node['arguments'];
            $each = $arguments['each'] ?? null;
            $list = is_array($each) && ($each[AstNodeKind::KEY] ?? null) === AstNodeKind::PATH
                ? $this->path($each)
                : null;
            if ($list !== null) {
                $limit = $arguments['limit'] ?? null;
                if (is_int($limit) && $limit > 0 && !array_key_exists('if', $arguments)) {
                    $limited[$list] = max($limited[$list] ?? 0, $limit);
                } else {
                    $whole[$list] = true;
                }
                unset($arguments['each']);
            }
            $this->walk($arguments, $limited, $whole, $reads);
            $this->walk($node['body'] ?? null, $limited, $whole, $reads);

            return;
        }
        foreach ($node as $child) {
            $this->walk($child, $limited, $whole, $reads);
        }
    }

    /**
     * The dot-separated path of a path node; null when it is not one
     *
     * @param array<mixed> $node
     * @return string|null
     */
    private function path(array $node): ?string
    {
        $segments = $node['segments'] ?? null;
        if (!is_array($segments) || $segments === []) {
            return null;
        }
        $parts = [];
        foreach ($segments as $segment) {
            if (!is_string($segment) && !is_int($segment)) {
                return null;
            }
            $parts[] = (string) $segment;
        }

        return implode('.', $parts);
    }
}
