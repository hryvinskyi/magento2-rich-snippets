<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Library;

use Hryvinskyi\RichSnippets\Api\Data\AnswerOptionInterface;
use Hryvinskyi\RichSnippets\Api\Data\LibraryTemplateInterface;
use Hryvinskyi\RichSnippets\Api\Data\QuestionSuggestionsInterface;
use Hryvinskyi\RichSnippets\Api\Exception\LibraryException;
use Hryvinskyi\RichSnippets\Api\Exception\LibraryReason;
use Hryvinskyi\RichSnippets\Api\QuestionSuggesterPoolInterface;

/**
 * Asks a template's questions in a store view and picks the answers chosen by their keys
 *
 * The answers are always the suggesters' own: a chosen key only selects one of them, so a fragment never comes from
 * the request. A question left unanswered takes the suggested answer.
 */
class AnswerResolver
{
    /**
     * @param QuestionSuggesterPoolInterface $suggesters
     */
    public function __construct(private readonly QuestionSuggesterPoolInterface $suggesters)
    {
    }

    /**
     * The answers each question of a template offers
     *
     * @param LibraryTemplateInterface $template
     * @param int $storeId
     * @return list<QuestionSuggestionsInterface>
     */
    public function suggestions(LibraryTemplateInterface $template, int $storeId): array
    {
        $suggestions = [];
        foreach ($template->getQuestions() as $question) {
            $suggestions[] = $this->suggesters->get($question->getSuggester())->suggest($question, $storeId);
        }

        return $suggestions;
    }

    /**
     * The chosen answer to each question, by question code
     *
     * @param LibraryTemplateInterface $template
     * @param array<string,string> $chosen the chosen answer's key by question code
     * @param int $storeId
     * @return array<string, AnswerOptionInterface>
     * @throws LibraryException when a question or an answer is unknown
     */
    public function chosen(LibraryTemplateInterface $template, array $chosen, int $storeId): array
    {
        $this->refuseUnknownQuestions($template, $chosen);
        $answers = [];
        foreach ($this->suggestions($template, $storeId) as $suggestions) {
            $question = $suggestions->getQuestion();
            $key = $chosen[$question] ?? $suggestions->getDefault();
            $answers[$question] = $this->option($suggestions, $key) ?? throw new LibraryException(
                LibraryReason::AnswerUnknown,
                ['question' => $question, 'answer' => $key]
            );
        }

        return $answers;
    }

    /**
     * Refuse an answer to a question the template does not ask
     *
     * @param LibraryTemplateInterface $template
     * @param array<string,string> $chosen
     * @return void
     * @throws LibraryException
     */
    private function refuseUnknownQuestions(LibraryTemplateInterface $template, array $chosen): void
    {
        $asked = [];
        foreach ($template->getQuestions() as $question) {
            $asked[$question->getCode()] = true;
        }
        foreach (array_keys($chosen) as $question) {
            if (!isset($asked[$question])) {
                throw new LibraryException(
                    LibraryReason::QuestionUnknown,
                    ['template' => $template->getCode(), 'question' => (string) $question]
                );
            }
        }
    }

    /**
     * The answer with a key
     *
     * @param QuestionSuggestionsInterface $suggestions
     * @param string $key
     * @return AnswerOptionInterface|null
     */
    private function option(QuestionSuggestionsInterface $suggestions, string $key): ?AnswerOptionInterface
    {
        foreach ($suggestions->getOptions() as $option) {
            if ($option->getKey() === $key) {
                return $option;
            }
        }

        return null;
    }
}
