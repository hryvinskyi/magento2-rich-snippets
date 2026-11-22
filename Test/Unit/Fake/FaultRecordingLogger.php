<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Test\Unit\Fake;

use Psr\Log\AbstractLogger;

/**
 * A logger that keeps each record's level and the exception it carries
 */
class FaultRecordingLogger extends AbstractLogger
{
    /**
     * @var list<array{0: mixed, 1: mixed}>
     */
    public array $faults = [];

    /**
     * @inheritDoc
     */
    public function log($level, string|\Stringable $message, array $context = []): void
    {
        $this->faults[] = [$level, $context['exception'] ?? null];
    }
}
