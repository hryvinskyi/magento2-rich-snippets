<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Library\Factory;

use Hryvinskyi\RichSnippets\Api\Data\AnswerOptionInterface;
use Hryvinskyi\RichSnippets\Api\Data\AnswerOptionInterfaceFactory;
use Hryvinskyi\RichSnippets\Api\Data\HintTone;
use Hryvinskyi\RichSnippets\Api\Data\QuestionSuggestionsInterface;
use Hryvinskyi\RichSnippets\Api\Data\QuestionSuggestionsInterfaceFactory;
use Hryvinskyi\RichSnippets\Model\Library\Question\AnswerFactoryInterface;

/**
 * Creates answers and suggestions through the factories Magento generates for their interfaces
 */
class AnswerFactoryAdapter implements AnswerFactoryInterface
{
    /**
     * @param AnswerOptionInterfaceFactory $options
     * @param QuestionSuggestionsInterfaceFactory $suggestions
     */
    public function __construct(
        private readonly AnswerOptionInterfaceFactory $options,
        private readonly QuestionSuggestionsInterfaceFactory $suggestions
    ) {
    }

    /**
     * @inheritDoc
     */
    public function fragment(
        string $key,
        string $label,
        mixed $fragment,
        string $hint,
        HintTone $tone,
        ?int $fillCount = null
    ): AnswerOptionInterface {
        return $this->options->create([
            'key' => $key,
            'label' => $label,
            'leftOut' => false,
            'fragment' => $fragment,
            'hint' => $hint,
            'tone' => $tone,
            'fillCount' => $fillCount,
        ]);
    }

    /**
     * @inheritDoc
     */
    public function leaveOut(string $key, string $label, string $hint, HintTone $tone): AnswerOptionInterface
    {
        return $this->options->create([
            'key' => $key,
            'label' => $label,
            'leftOut' => true,
            'fragment' => null,
            'hint' => $hint,
            'tone' => $tone,
            'fillCount' => null,
        ]);
    }

    /**
     * @inheritDoc
     */
    public function suggestions(
        string $question,
        string $label,
        array $options,
        string $default,
        ?int $total
    ): QuestionSuggestionsInterface {
        return $this->suggestions->create([
            'question' => $question,
            'label' => $label,
            'options' => $options,
            'default' => $default,
            'total' => $total,
        ]);
    }
}
