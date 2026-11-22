<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Admin\Library;

use Hryvinskyi\AdminSetupWizard\Api\Exception\InvalidWizardRequestException;
use Hryvinskyi\RichSnippets\Api\Data\AnswerOptionInterface;
use Hryvinskyi\RichSnippets\Api\Data\QuestionSuggestionsInterface;
use Hryvinskyi\RichSnippets\Api\Exception\LibraryException;
use Hryvinskyi\RichSnippets\Api\Exception\MalformedLibraryTemplateException;
use Hryvinskyi\RichSnippets\Api\LibraryInterface;

/**
 * The questions a template asks when it is added, with the answers this shop is offered and the one suggested in
 * advance, as the add drawer shows them
 *
 * The answer is `{"template", "questions": [{"question", "label", "default", "total", "options": [{"key", "label",
 * "leftOut", "hint", "tone", "fillCount"}]}]}`: `default` is the key of the suggested answer, `total` how many product
 * pages the fill counts are out of (null when no answer reads an attribute), `tone` how the hint under the select
 * reads (`ok` or `warn`). A template the library does not have answers 400 `{"error": "template_unknown"}`.
 *
 * @phpstan-type Option array{key: string, label: string, leftOut: bool, hint: string, tone: string,
 *     fillCount: int|null}
 * @phpstan-type Question array{question: string, label: string, default: string, total: int|null,
 *     options: list<Option>}
 */
class LibraryQuestions
{
    /**
     * @param LibraryRequest $request
     * @param LibraryInterface $library
     */
    public function __construct(
        private readonly LibraryRequest $request,
        private readonly LibraryInterface $library
    ) {
    }

    /**
     * The questions of the template the query names: `?template=<code>`
     *
     * @param array<mixed> $query
     * @return array{template: string, questions: list<Question>}
     * @throws InvalidWizardRequestException when no template is named or the library has none with the code
     * @throws MalformedLibraryTemplateException
     */
    public function of(array $query): array
    {
        $code = $this->request->template($query);
        try {
            $suggestions = $this->library->suggestions($code);
        } catch (LibraryException $e) {
            throw new InvalidWizardRequestException($e->getReason()->value, 'The library refused the request.', $e);
        }

        return [
            'template' => $code,
            'questions' => array_map(
                fn (QuestionSuggestionsInterface $question): array => $this->question($question),
                $suggestions
            ),
        ];
    }

    /**
     * One question as the drawer shows it
     *
     * @param QuestionSuggestionsInterface $question
     * @return Question
     */
    private function question(QuestionSuggestionsInterface $question): array
    {
        return [
            'question' => $question->getQuestion(),
            'label' => (string) __($question->getLabel()),
            'default' => $question->getDefault(),
            'total' => $question->getTotal(),
            'options' => array_map(
                static fn (AnswerOptionInterface $option): array => [
                    'key' => $option->getKey(),
                    'label' => $option->getLabel(),
                    'leftOut' => $option->isLeftOut(),
                    'hint' => $option->getHint(),
                    'tone' => $option->getTone()->value,
                    'fillCount' => $option->getFillCount(),
                ],
                $question->getOptions()
            ),
        ];
    }
}
