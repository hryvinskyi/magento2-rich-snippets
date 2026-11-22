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
use Hryvinskyi\JsonTemplate\Api\RecordingTraceInterface;

/**
 * A recording trace filled by hand, entries in the order a test records them
 */
class RecordingTraceFake implements RecordingTraceInterface
{
    /**
     * @var list<TraceEntryInterface>
     */
    private array $entries = [];

    /**
     * @inheritDoc
     */
    public function isRecording(): bool
    {
        return true;
    }

    /**
     * @inheritDoc
     */
    public function record(string $path, TraceOutcome $outcome, array $sources): void
    {
        $this->entries[] = new RecordedTraceEntry($path, $outcome, $sources);
    }

    /**
     * @inheritDoc
     */
    public function note(string $path, string $code, array $params = []): void
    {
    }

    /**
     * @inheritDoc
     */
    public function getEntries(): array
    {
        return $this->entries;
    }

    /**
     * @inheritDoc
     */
    public function getEntry(string $path): ?TraceEntryInterface
    {
        $found = null;
        foreach ($this->entries as $entry) {
            $found = $entry->getPath() === $path ? $entry : $found;
        }

        return $found;
    }

    /**
     * @inheritDoc
     */
    public function getNotes(): array
    {
        return [];
    }
}
