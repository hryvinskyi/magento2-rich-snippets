<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Admin\Editor;

use Hryvinskyi\JsonTemplate\Api\Data\TraceEntryInterface;
use Hryvinskyi\RichSnippets\Api\Data\DraftPreviewInterface;
use Hryvinskyi\RichSnippets\Api\Data\PageMatchInterface;
use Hryvinskyi\RichSnippets\Api\Data\PreviewState;

/**
 * Writes a preview as the editor's Preview and Checks panels read it
 *
 * `{"state", "sample", "output", "trace", "message", "checks", "problems"}`: `sample` is the page as the sample
 * picker lists it (null for a page the store view does not have); `trace` is every node's path, outcome (`value`,
 * `empty`, `skipped`) and the fields an empty or skipped one read, which the builder's rows show as "empty here" and
 * "skipped here"; `message` is the title and text of the box shown instead of an output; `checks` the rows of the
 * Checks panel (see PreviewChecks); `problems` what stops an invalid draft from being shown.
 *
 * @phpstan-import-type Sample from EditorPresenter
 * @phpstan-import-type Problem from EditorPresenter
 * @phpstan-import-type Row from PreviewChecks
 * @phpstan-type Answer array{state: string, sample: Sample|null, output: array<mixed>|null,
 *     trace: list<array{path: string, outcome: string, sources: list<string>}>,
 *     message: array{title: string, text: string}|null, checks: list<Row>, problems: list<Problem>}
 */
class PreviewPresenter
{
    /**
     * @param EditorPresenter $presenter
     * @param PreviewChecks $checks
     */
    public function __construct(
        private readonly EditorPresenter $presenter,
        private readonly PreviewChecks $checks
    ) {
    }

    /**
     * The preview as the editor reads it
     *
     * @param DraftPreviewInterface $preview
     * @param PageMatchInterface|null $sample the sample page as the picker lists it
     * @param string $type the draft's schema.org type
     * @return Answer
     */
    public function present(DraftPreviewInterface $preview, ?PageMatchInterface $sample, string $type): array
    {
        $name = $sample?->getLabel() ?? $this->presenter->reference($preview->getPage());

        return [
            'state' => $preview->getState()->value,
            'sample' => $sample === null ? null : $this->presenter->sample($sample),
            'output' => $preview->getOutput(),
            'trace' => array_map(
                static fn (TraceEntryInterface $entry): array => [
                    'path' => $entry->getPath(),
                    'outcome' => $entry->getOutcome()->value,
                    'sources' => $entry->getSources(),
                ],
                $preview->getTrace()
            ),
            'message' => $this->message($preview->getState(), $name),
            'checks' => $this->checks->rows($preview, $type, $name),
            'problems' => $this->presenter->problems($preview->getProblems()),
        ];
    }

    /**
     * The box shown in place of an output; null when there is an output
     *
     * @param PreviewState $state
     * @param string $sample
     * @return array{title: string, text: string}|null
     */
    private function message(PreviewState $state, string $sample): ?array
    {
        [$title, $text] = match ($state) {
            PreviewState::Output => [null, null],
            PreviewState::NoType => [
                __('Nothing to show yet'),
                __('The output appears here as soon as the snippet has a type and one property with a value.'),
            ],
            PreviewState::Invalid => [
                __('Nothing to show yet'),
                __('The template has an error. The output appears here again once it is fixed.'),
            ],
            PreviewState::ConditionsNotMet => [
                __('Not output for %1', $sample),
                __('This page does not meet the conditions under “Only when”, in Pages.'),
            ],
            PreviewState::Empty => [
                __('Nothing is output'),
                __('Every property is empty for this page, so the whole snippet is left out.'),
            ],
        };

        return $title === null || $text === null ? null : ['title' => (string) $title, 'text' => (string) $text];
    }
}
