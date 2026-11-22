<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Editor;

/**
 * Where a template includes a snippet: the property path of each `@include` of its code
 *
 * The path names the properties on the way from the root, the way the editor shows it
 * (`offers.hasMerchantReturnPolicy`): a list's items and a repeated object's items share their property's path, a
 * value an object stands for (`@value`) is its object's, and the language's other directive keys are no properties.
 * The include at the root itself has the empty path.
 */
class IncludeSites
{
    private const INCLUDE = '@include';
    private const VALUE = '@value';
    private const DIRECTIVES = ['@if', '@each', '@as', '@limit', self::INCLUDE];

    /**
     * The paths of a template's includes of a code, in template order, each once
     *
     * @param string $template the JSON template
     * @param string $code
     * @return list<string> none for a template that is not JSON
     */
    public function of(string $template, string $code): array
    {
        $paths = [];
        $this->walk(json_decode($template, true), '', $code, $paths);

        return array_map('strval', array_keys($paths));
    }

    /**
     * Note the includes of a code in a value and in everything inside it
     *
     * @param mixed $value
     * @param string $path
     * @param string $code
     * @param array<string,true> $paths
     * @return void
     */
    private function walk(mixed $value, string $path, string $code, array &$paths): void
    {
        if (!is_array($value)) {
            return;
        }
        if (array_is_list($value)) {
            foreach ($value as $item) {
                $this->walk($item, $path, $code, $paths);
            }

            return;
        }
        if (($value[self::INCLUDE] ?? null) === $code) {
            $paths[$path] = true;
        }
        foreach ($value as $key => $child) {
            $key = (string) $key;
            if (in_array($key, self::DIRECTIVES, true)) {
                continue;
            }
            $inner = match (true) {
                $key === self::VALUE => $path,
                $path === '' => $key,
                default => $path . '.' . $key,
            };
            $this->walk($child, $inner, $code, $paths);
        }
    }
}
