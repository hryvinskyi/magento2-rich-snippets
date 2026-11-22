<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Clock;

/**
 * The server's clock
 */
class SystemClock implements ClockInterface
{
    /**
     * @inheritDoc
     */
    public function now(): int
    {
        return time();
    }
}
