<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Draft;

use Hryvinskyi\JsonTemplate\Api\Data\TraceEntryInterface;
use Hryvinskyi\RichSnippets\Api\Data\ConditionMismatchInterface;
use Hryvinskyi\RichSnippets\Api\Data\DraftPreviewInterface;
use Hryvinskyi\RichSnippets\Api\Data\DraftProblemInterface;
use Hryvinskyi\RichSnippets\Api\Data\OutputCheckInterface;
use Hryvinskyi\RichSnippets\Api\Data\PageEntityInterface;
use Hryvinskyi\RichSnippets\Api\Data\PreviewState;
use Hryvinskyi\RichSnippets\Api\Data\SnippetInterface;

/**
 * A draft evaluated for one sample page
 */
class DraftPreview implements DraftPreviewInterface
{
    /**
     * @param PreviewState $state
     * @param PageEntityInterface $page
     * @param array<mixed>|null $output
     * @param list<TraceEntryInterface> $trace
     * @param list<ConditionMismatchInterface> $mismatches
     * @param list<SnippetInterface> $instead
     * @param OutputCheckInterface|null $check
     * @param list<DraftProblemInterface> $problems
     * @param string $conditionText
     * @throws \InvalidArgumentException when the output or the check is there without the state that has them
     */
    public function __construct(
        private readonly PreviewState $state,
        private readonly PageEntityInterface $page,
        private readonly ?array $output = null,
        private readonly array $trace = [],
        private readonly array $mismatches = [],
        private readonly array $instead = [],
        private readonly ?OutputCheckInterface $check = null,
        private readonly array $problems = [],
        private readonly string $conditionText = ''
    ) {
        if (($state === PreviewState::Output) !== ($output !== null) || ($check !== null && $output === null)) {
            throw new \InvalidArgumentException('Only a preview that writes a block has an output and a check.');
        }
    }

    /**
     * @inheritDoc
     */
    public function getState(): PreviewState
    {
        return $this->state;
    }

    /**
     * @inheritDoc
     */
    public function getPage(): PageEntityInterface
    {
        return $this->page;
    }

    /**
     * @inheritDoc
     */
    public function getOutput(): ?array
    {
        return $this->output;
    }

    /**
     * @inheritDoc
     */
    public function getTrace(): array
    {
        return $this->trace;
    }

    /**
     * @inheritDoc
     */
    public function getMismatches(): array
    {
        return $this->mismatches;
    }

    /**
     * @inheritDoc
     */
    public function getInstead(): array
    {
        return $this->instead;
    }

    /**
     * @inheritDoc
     */
    public function getCheck(): ?OutputCheckInterface
    {
        return $this->check;
    }

    /**
     * @inheritDoc
     */
    public function getProblems(): array
    {
        return $this->problems;
    }

    /**
     * @inheritDoc
     */
    public function getConditionText(): string
    {
        return $this->conditionText;
    }
}
