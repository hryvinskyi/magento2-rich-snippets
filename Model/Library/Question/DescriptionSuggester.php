<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Library\Question;

use Hryvinskyi\AdminUi\Api\Page\PluralPhrasesInterface;
use Hryvinskyi\RichSnippets\Api\Data\HintTone;
use Hryvinskyi\RichSnippets\Api\Data\LibraryQuestionInterface;
use Hryvinskyi\RichSnippets\Api\Data\QuestionSuggestionsInterface;
use Hryvinskyi\RichSnippets\Api\QuestionSuggesterInterface;

/**
 * Which text describes a product: one of the configured description attributes, with its HTML removed and cut at
 * 5,000 characters
 *
 * The most filled one is suggested. Leaving the description out is offered only when the shop has none of them.
 */
class DescriptionSuggester implements QuestionSuggesterInterface
{
    private const LEAVE_OUT = 'leave_out';

    /**
     * @param AttributeAnswers $attributeAnswers
     * @param AnswerFactoryInterface $answers
     * @param PluralPhrasesInterface $plurals
     * @param array<string,string> $attributes the candidate attribute codes, in the order of preference
     */
    public function __construct(
        private readonly AttributeAnswers $attributeAnswers,
        private readonly AnswerFactoryInterface $answers,
        private readonly PluralPhrasesInterface $plurals,
        private readonly array $attributes = []
    ) {
    }

    /**
     * @inheritDoc
     */
    public function suggest(LibraryQuestionInterface $question, int $storeId): QuestionSuggestionsInterface
    {
        $root = $question->getRoot();
        $found = $this->attributeAnswers->answers(
            array_values($this->attributes),
            $storeId,
            static fn (string $code): string => '{{' . $root . '.' . $code . ' | plain | limit:5000}}',
            static fn (string $code, string $label): string => $label,
            fn (int $filled, int $total): string => $this->plurals->sentence(
                $total,
                __('Filled for %1 of %2 product. HTML is removed, cut at 5,000 characters.', $filled, $total),
                __('Filled for %1 of %2 products. HTML is removed, cut at 5,000 characters.', $filled, $total)
            )
        );
        $options = $found['options'];
        if ($options === []) {
            $options[] = $this->answers->leaveOut(
                self::LEAVE_OUT,
                (string) __('Leave it out'),
                (string) __('Google recommends a description.'),
                HintTone::Warn
            );
        }

        return $this->answers->suggestions(
            $question->getCode(),
            $question->getLabel(),
            $options,
            $found['best'] ?? $options[0]->getKey(),
            $found['total']
        );
    }
}
