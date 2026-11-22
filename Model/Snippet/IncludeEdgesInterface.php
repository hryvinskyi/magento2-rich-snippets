<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Snippet;

/**
 * The include graph of the stored snippets
 */
interface IncludeEdgesInterface
{
    /**
     * The codes each stored snippet's template includes, by the snippet's code
     *
     * The codes come from the compiled template, in the order the template first names them; a stored template
     * that no longer compiles includes nothing.
     *
     * @return array<string, list<string>>
     */
    public function edges(): array;
}
