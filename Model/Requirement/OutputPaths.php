<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Requirement;

/**
 * Whether an evaluated block carries a property path
 *
 * A path is property names joined by dots. A list on the way holds the rest of the path only when it has items and
 * every item holds it, so `itemListElement.position` needs a position on each crumb and `offers.price` a price on
 * each offer. At the end of the path any value but null, an empty string and an empty list counts.
 */
class OutputPaths
{
    /**
     * Whether the block carries the path
     *
     * @param array<mixed> $output
     * @param string $path
     * @return bool
     */
    public function has(array $output, string $path): bool
    {
        return $this->holds($output, $path === '' ? [] : explode('.', $path));
    }

    /**
     * Whether a value holds the rest of a path
     *
     * @param mixed $value
     * @param list<string> $segments
     * @return bool
     */
    private function holds(mixed $value, array $segments): bool
    {
        if ($value === null || $value === '' || $value === []) {
            return false;
        }
        if (is_array($value) && array_is_list($value)) {
            foreach ($value as $item) {
                if (!$this->holds($item, $segments)) {
                    return false;
                }
            }

            return true;
        }
        if ($segments === []) {
            return true;
        }
        if (!is_array($value)) {
            return false;
        }
        $name = array_shift($segments);

        return array_key_exists($name, $value) && $this->holds($value[$name], $segments);
    }
}
