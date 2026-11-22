<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Listing;

/**
 * Counts the properties a template writes, as the list's "21 properties" means it: the properties of the root,
 * and those of each object directly inside the root
 *
 * A key starting with `@` is no property (`@type`, `@context`, a directive). The inside of a list, of a value
 * (`@value`) and of an included snippet (`@include`) is not counted: such an object counts once, as the property
 * that holds it. A template that is not JSON with an object root counts nothing.
 */
class PropertyCount
{
    private const DIRECTIVE_PREFIX = '@';
    private const OPAQUE_DIRECTIVES = ['@each', '@value', '@include'];

    /**
     * The template's property count
     *
     * @param string $template
     * @return int
     */
    public function of(string $template): int
    {
        $root = json_decode($template, true);
        if (!is_array($root) || array_is_list($root)) {
            return 0;
        }
        $count = 0;
        foreach ($root as $key => $value) {
            if ($this->isProperty($key)) {
                $count += 1 + $this->childCount($value);
            }
        }

        return $count;
    }

    /**
     * The properties of an object directly inside the root; none for anything else
     *
     * @param mixed $value
     * @return int
     */
    private function childCount(mixed $value): int
    {
        if (!is_array($value) || array_is_list($value)
            || array_intersect(array_map('strval', array_keys($value)), self::OPAQUE_DIRECTIVES) !== []
        ) {
            return 0;
        }

        return count(array_filter(array_keys($value), fn (int|string $key): bool => $this->isProperty($key)));
    }

    /**
     * Whether a key is a property
     *
     * @param int|string $key
     * @return bool
     */
    private function isProperty(int|string $key): bool
    {
        return !str_starts_with((string) $key, self::DIRECTIVE_PREFIX);
    }
}
