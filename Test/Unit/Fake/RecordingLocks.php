<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Test\Unit\Fake;

use Magento\Framework\Lock\LockManagerInterface;

/**
 * Locks in memory, some of which another process already holds
 */
class RecordingLocks implements LockManagerInterface
{
    /**
     * @var array<string, true>
     */
    public array $held = [];

    /**
     * @var list<string>
     */
    public array $calls = [];

    /**
     * @param list<string> $heldElsewhere
     */
    public function __construct(private readonly array $heldElsewhere = [])
    {
    }

    /**
     * @inheritDoc
     */
    public function lock(string $name, int $timeout = -1): bool
    {
        $this->calls[] = 'lock ' . $name . ' ' . $timeout;
        if (in_array($name, $this->heldElsewhere, true)) {
            return false;
        }
        $this->held[$name] = true;

        return true;
    }

    /**
     * @inheritDoc
     */
    public function unlock(string $name): bool
    {
        $this->calls[] = 'unlock ' . $name;
        unset($this->held[$name]);

        return true;
    }

    /**
     * @inheritDoc
     */
    public function isLocked(string $name): bool
    {
        return isset($this->held[$name]) || in_array($name, $this->heldElsewhere, true);
    }
}
