<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Api\Data;

use Hryvinskyi\JsonTemplate\Api\Data\TraceEntryInterface;

/**
 * A draft evaluated for one sample page as the storefront of the page's store view evaluates it
 *
 * A building block is shown as it appears inside the snippet that includes it: without a root `@context`.
 *
 * @api
 */
interface DraftPreviewInterface
{
    /**
     * What the draft gives on the page
     *
     * @return PreviewState
     */
    public function getState(): PreviewState;

    /**
     * The sample page
     *
     * @return PageEntityInterface
     */
    public function getPage(): PageEntityInterface;

    /**
     * The block written on the page; null unless the state is Output
     *
     * @return array<mixed>|null
     */
    public function getOutput(): ?array;

    /**
     * What became of every node of the template on the page, in the order the nodes finished
     *
     * Empty unless the template was evaluated (Output and Empty).
     *
     * @return list<TraceEntryInterface>
     */
    public function getTrace(): array;

    /**
     * The conditions the page does not meet, in the draft's order; empty unless the state is ConditionsNotMet
     *
     * @return list<ConditionMismatchInterface>
     */
    public function getMismatches(): array;

    /**
     * The stored page snippets of the draft's type the page writes in its place, by sort order
     *
     * Known only when the state is ConditionsNotMet; the draft's own stored snippet is never one of them.
     *
     * @return list<SnippetInterface>
     */
    public function getInstead(): array;

    /**
     * The block compared with Google's requirements for its type; null unless the state is Output
     *
     * @return OutputCheckInterface|null
     */
    public function getCheck(): ?OutputCheckInterface;

    /**
     * What stops the draft from being shown; empty unless the state is Invalid
     *
     * @return list<DraftProblemInterface>
     */
    public function getProblems(): array;

    /**
     * The draft's conditions in words ("Product type is not Configurable")
     *
     * Empty unless the state is ConditionsNotMet.
     *
     * @return string
     */
    public function getConditionText(): string;
}
