<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Test\Unit\Fake;

use Hryvinskyi\RichSnippets\Model\Clock\ClockInterface;

/**
 * A clock that tells the time it is set to
 */
class FixedClock implements ClockInterface
{
    /**
     * @param int $now
     */
    public function __construct(public int $now)
    {
    }

    /**
     * @inheritDoc
     */
    public function now(): int
    {
        return $this->now;
    }
}
