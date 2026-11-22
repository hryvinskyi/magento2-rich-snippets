<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Snippet;

/**
 * Which snippets include which: the loops a template would close and the snippets a change reaches
 *
 * The graph is given as each snippet's code and the codes its template includes, in the order the template names
 * them. A code no snippet has is a leaf: it includes nothing.
 */
class IncludeGraph
{
    /**
     * The loop a snippet with these includes would close, from its code back to it; empty when there is none
     *
     * The shortest loop is returned, so the admin can name the snippets on it: `[a, b, a]` for a snippet `a` that
     * includes `b`, which includes `a`; `[a, a]` for one that includes itself.
     *
     * @param string $code
     * @param list<string> $includes the codes the snippet's template includes
     * @param array<string,list<string>> $edges the codes each stored snippet includes, by code
     * @return list<string>
     */
    public function loopThrough(string $code, array $includes, array $edges): array
    {
        $edges[$code] = $includes;
        $parents = [];
        $queue = [$code];
        while ($queue !== []) {
            $node = array_shift($queue);
            foreach ($edges[$node] ?? [] as $child) {
                if ($child === $code) {
                    return $this->pathTo($node, $code, $parents);
                }
                if (!array_key_exists($child, $parents)) {
                    $parents[$child] = $node;
                    $queue[] = $child;
                }
            }
        }

        return [];
    }

    /**
     * The codes of the snippets that include a snippet, directly or through others, nearest first
     *
     * @param string $code
     * @param array<string,list<string>> $edges
     * @return list<string>
     */
    public function includersOf(string $code, array $edges): array
    {
        $includers = [];
        $seen = [$code => true];
        $queue = [$code];
        while ($queue !== []) {
            $node = array_shift($queue);
            foreach ($this->directIncludersOf($node, $edges) as $includer) {
                if (!isset($seen[$includer])) {
                    $seen[$includer] = true;
                    $includers[] = $includer;
                    $queue[] = $includer;
                }
            }
        }

        return $includers;
    }

    /**
     * The codes of the snippets whose template includes a snippet itself, in code order; never the snippet
     *
     * @param string $code
     * @param array<string,list<string>> $edges
     * @return list<string>
     */
    public function directIncludersOf(string $code, array $edges): array
    {
        $includers = [];
        foreach ($edges as $includer => $includes) {
            $includer = (string) $includer;
            if ($includer !== $code && in_array($code, $includes, true)) {
                $includers[] = $includer;
            }
        }
        sort($includers, SORT_STRING);

        return $includers;
    }

    /**
     * The path from the start through the parents to a node, closed by the start again
     *
     * @param string $node the last node before the loop closes
     * @param string $start
     * @param array<string,string> $parents each reached node's predecessor
     * @return list<string>
     */
    private function pathTo(string $node, string $start, array $parents): array
    {
        $path = [$start];
        while ($node !== $start) {
            $path[] = $node;
            $node = $parents[$node];
        }
        $path[] = $start;

        return array_reverse($path);
    }
}
