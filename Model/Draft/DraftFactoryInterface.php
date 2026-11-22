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
use Hryvinskyi\RichSnippets\Api\Data\DraftSaveResultInterface;
use Hryvinskyi\RichSnippets\Api\Data\DraftSnippetInterface;
use Hryvinskyi\RichSnippets\Api\Data\OutputCheckInterface;
use Hryvinskyi\RichSnippets\Api\Data\PageEntityInterface;
use Hryvinskyi\RichSnippets\Api\Data\PreviewState;
use Hryvinskyi\RichSnippets\Api\Data\SnippetInterface;

/**
 * Creates the values the editor's use cases answer with, from typed arguments
 */
interface DraftFactoryInterface
{
    /**
     * A draft
     *
     * @param int|null $id
     * @param string $name
     * @param string $template
     * @param bool $isBlock
     * @param bool $active
     * @param list<string> $pageKinds
     * @param string $conditions the expression builder's text
     * @param list<int> $storeIds
     * @return DraftSnippetInterface
     * @throws \InvalidArgumentException when the ID is not positive
     */
    public function draft(
        ?int $id,
        string $name,
        string $template,
        bool $isBlock,
        bool $active,
        array $pageKinds,
        string $conditions,
        array $storeIds
    ): DraftSnippetInterface;

    /**
     * A problem with a draft
     *
     * @param string $part one of DraftProblemInterface::PART_*
     * @param string $path
     * @param string $code
     * @param array<string,scalar|null> $params
     * @param int|null $offset
     * @param bool $blocking
     * @return DraftProblemInterface
     * @throws \InvalidArgumentException when a value breaks a problem's shape
     */
    public function problem(
        string $part,
        string $path,
        string $code,
        array $params = [],
        ?int $offset = null,
        bool $blocking = true
    ): DraftProblemInterface;

    /**
     * A preview
     *
     * @param PreviewState $state
     * @param PageEntityInterface $page
     * @param array<mixed>|null $output
     * @param list<TraceEntryInterface> $trace
     * @param list<ConditionMismatchInterface> $mismatches
     * @param list<SnippetInterface> $instead
     * @param OutputCheckInterface|null $check
     * @param list<DraftProblemInterface> $problems
     * @param string $conditionText the draft's conditions in words, for a page that does not meet them
     * @return DraftPreviewInterface
     * @throws \InvalidArgumentException when the output or the check is there without the state that has them
     */
    public function preview(
        PreviewState $state,
        PageEntityInterface $page,
        ?array $output = null,
        array $trace = [],
        array $mismatches = [],
        array $instead = [],
        ?OutputCheckInterface $check = null,
        array $problems = [],
        string $conditionText = ''
    ): DraftPreviewInterface;

    /**
     * What saving a draft came to
     *
     * @param SnippetInterface|null $snippet
     * @param list<DraftProblemInterface> $problems
     * @return DraftSaveResultInterface
     * @throws \InvalidArgumentException unless there is either a snippet or a problem
     */
    public function saveResult(?SnippetInterface $snippet, array $problems = []): DraftSaveResultInterface;
}
