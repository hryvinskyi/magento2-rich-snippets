<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Api;

/**
 * Hands out the code of a new snippet, from its name
 *
 * Every way a snippet is created goes through it, since the admin never asks for a code. The code is the name
 * transliterated into the code grammar; when a snippet has it already, `-2`, `-3` … is added. The database's
 * unique index on the code still decides when two creations race for the same code.
 *
 * @api
 */
interface CodeAllocatorInterface
{
    /**
     * A code no stored snippet has, made from a name
     *
     * @param string $name
     * @return string
     */
    public function allocate(string $name): string;
}
