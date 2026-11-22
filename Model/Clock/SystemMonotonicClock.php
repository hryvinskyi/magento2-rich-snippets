<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Clock;

/**
 * The system's high-resolution monotonic clock
 */
class SystemMonotonicClock implements MonotonicClockInterface
{
    /**
     * @inheritDoc
     */
    public function seconds(): float
    {
        return hrtime(true) / 1e9;
    }
}
