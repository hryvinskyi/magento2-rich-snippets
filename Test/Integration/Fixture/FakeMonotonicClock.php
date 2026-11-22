<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Test\Integration\Fixture;

use Hryvinskyi\RichSnippets\Model\Clock\MonotonicClockInterface;

/**
 * A monotonic clock a test moves by hand
 */
class FakeMonotonicClock implements MonotonicClockInterface
{
    /**
     * @var float
     */
    private float $now = 1000.0;

    /**
     * @inheritDoc
     */
    public function seconds(): float
    {
        return $this->now;
    }

    /**
     * Move the clock forward
     *
     * @param float $seconds
     * @return void
     */
    public function advance(float $seconds): void
    {
        $this->now += $seconds;
    }
}
