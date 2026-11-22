<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Library\Question;

use Hryvinskyi\RichSnippets\Api\Data\AnswerOptionInterface;
use Hryvinskyi\RichSnippets\Api\Data\HintTone;
use Hryvinskyi\RichSnippets\Api\Data\QuestionSuggestionsInterface;

/**
 * Creates the answers suggesters offer, and the set of them for one question
 */
interface AnswerFactoryInterface
{
    /**
     * An answer that puts a template fragment in the placeholder's place
     *
     * @param string $key
     * @param string $label
     * @param mixed $fragment
     * @param string $hint
     * @param HintTone $tone
     * @param int|null $fillCount
     * @return AnswerOptionInterface
     */
    public function fragment(
        string $key,
        string $label,
        mixed $fragment,
        string $hint,
        HintTone $tone,
        ?int $fillCount = null
    ): AnswerOptionInterface;

    /**
     * An answer that leaves the property out
     *
     * @param string $key
     * @param string $label
     * @param string $hint
     * @param HintTone $tone
     * @return AnswerOptionInterface
     */
    public function leaveOut(string $key, string $label, string $hint, HintTone $tone): AnswerOptionInterface;

    /**
     * The answers to a question
     *
     * @param string $question
     * @param string $label
     * @param list<AnswerOptionInterface> $options
     * @param string $default
     * @param int|null $total
     * @return QuestionSuggestionsInterface
     * @throws \InvalidArgumentException when there is no answer, two share a key, or the default is none of them
     */
    public function suggestions(
        string $question,
        string $label,
        array $options,
        string $default,
        ?int $total
    ): QuestionSuggestionsInterface;
}
