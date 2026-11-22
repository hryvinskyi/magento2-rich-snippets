<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Library;

/**
 * Names a copy so no two snippets share a name: the name itself when it is free, else the name with the first free
 * number from 2 (`Product 2`, `Product 3` …)
 */
class CopyNames
{
    private const FIRST_NUMBER = 2;

    /**
     * The first free name of a name's sequence
     *
     * @param string $name
     * @param array<string,true> $taken the names in use
     * @return string
     */
    public function next(string $name, array $taken): string
    {
        if (!isset($taken[$name])) {
            return $name;
        }
        $number = self::FIRST_NUMBER;
        while (isset($taken[$name . ' ' . $number])) {
            $number++;
        }

        return $name . ' ' . $number;
    }
}
