<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Api\Data;

/**
 * One thing an import found wrong with a snippet of the file, with the values it refers to
 *
 * @api
 */
interface ImportProblemInterface
{
    /**
     * What is wrong
     *
     * @return ImportProblemReason
     */
    public function getReason(): ImportProblemReason;

    /**
     * The values the reason refers to, by name
     *
     * @return array<string,string>
     */
    public function getParams(): array;
}
