<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Clock;

/**
 * The time now, for whatever the module stamps with a time
 */
interface ClockInterface
{
    /**
     * Now, in Unix seconds
     *
     * @return int
     */
    public function now(): int;
}
