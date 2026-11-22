<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Clock;

/**
 * A clock that only goes forward, for measuring durations and keeping deadlines
 *
 * Its readings mean nothing on their own: only the difference between two of them does.
 */
interface MonotonicClockInterface
{
    /**
     * The time since an arbitrary point, in seconds, with a fraction
     *
     * @return float
     */
    public function seconds(): float;
}
