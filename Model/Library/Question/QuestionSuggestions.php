<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Library\Question;

use Hryvinskyi\RichSnippets\Api\Data\AnswerOptionInterface;
use Hryvinskyi\RichSnippets\Api\Data\QuestionSuggestionsInterface;

/**
 * The answers a library question offers, with the one suggested in advance
 */
class QuestionSuggestions implements QuestionSuggestionsInterface
{
    /**
     * @param string $question
     * @param string $label
     * @param list<AnswerOptionInterface> $options
     * @param string $default
     * @param int|null $total
     * @throws \InvalidArgumentException when there is no answer, two answers share a key, or the suggested key is
     *     none of the answers'
     */
    public function __construct(
        private readonly string $question,
        private readonly string $label,
        private readonly array $options,
        private readonly string $default,
        private readonly ?int $total = null
    ) {
        $keys = array_map(static fn (AnswerOptionInterface $option): string => $option->getKey(), $options);
        if ($keys === [] || count(array_unique($keys)) !== count($keys) || !in_array($default, $keys, true)) {
            throw new \InvalidArgumentException(sprintf(
                'The question "%s" needs answers with keys of their own, the suggested one among them.',
                $question
            ));
        }
    }

    /**
     * @inheritDoc
     */
    public function getQuestion(): string
    {
        return $this->question;
    }

    /**
     * @inheritDoc
     */
    public function getLabel(): string
    {
        return $this->label;
    }

    /**
     * @inheritDoc
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    /**
     * @inheritDoc
     */
    public function getDefault(): string
    {
        return $this->default;
    }

    /**
     * @inheritDoc
     */
    public function getTotal(): ?int
    {
        return $this->total;
    }
}
