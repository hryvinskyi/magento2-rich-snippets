<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Test\Unit\Fake;

use Hryvinskyi\JsonTemplate\Api\Data\TraceEntryInterface;
use Hryvinskyi\JsonTemplate\Api\Data\TraceOutcome;

/**
 * One entry of a recording trace: where it was written, what happened there and which sources it read
 */
class RecordedTraceEntry implements TraceEntryInterface
{
    /**
     * @param string $path
     * @param TraceOutcome $outcome
     * @param list<string> $sources
     */
    public function __construct(
        private readonly string $path,
        private readonly TraceOutcome $outcome,
        private readonly array $sources
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * @inheritDoc
     */
    public function getOutcome(): TraceOutcome
    {
        return $this->outcome;
    }

    /**
     * @inheritDoc
     */
    public function getSources(): array
    {
        return $this->sources;
    }
}
