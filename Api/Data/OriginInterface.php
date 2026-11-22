<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Api\Data;

/**
 * The library template a snippet was added from, and its version at the time
 *
 * @api
 */
interface OriginInterface
{
    /**
     * The library template's code
     *
     * @return string
     */
    public function getCode(): string;

    /**
     * The library template's version when the snippet was added, from 1
     *
     * @return int
     */
    public function getVersion(): int;
}
