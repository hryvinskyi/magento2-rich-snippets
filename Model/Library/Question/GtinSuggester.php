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
use Hryvinskyi\RichSnippets\Model\Catalog\ProductAttributes;

/**
 * Which attribute holds a product's GTIN: every attribute whose code or label names a barcode, or nothing
 *
 * An attribute is a candidate when a word of its code (split at `_` and digits) or of its label is one of the
 * configured terms (`ean`, `upc`, `gtin`, `barcode` …). The most filled one is suggested; when none is filled,
 * leaving the GTIN out is.
 */
class GtinSuggester implements QuestionSuggesterInterface
{
    private const LEAVE_OUT = 'leave_out';
    private const CODE_WORDS = '/[_\d]+/';
    private const LABEL_WORDS = '/[^\p{L}]+/u';

    /**
     * @param AttributeAnswers $attributeAnswers
     * @param ProductAttributes $productAttributes
     * @param AnswerFactoryInterface $answers
     * @param PluralPhrasesInterface $plurals
     * @param array<string,string> $terms the words that name a barcode, in lower case
     */
    public function __construct(
        private readonly AttributeAnswers $attributeAnswers,
        private readonly ProductAttributes $productAttributes,
        private readonly AnswerFactoryInterface $answers,
        private readonly PluralPhrasesInterface $plurals,
        private readonly array $terms = []
    ) {
    }

    /**
     * @inheritDoc
     */
    public function suggest(LibraryQuestionInterface $question, int $storeId): QuestionSuggestionsInterface
    {
        $byCode = [];
        $candidates = [];
        foreach ($this->productAttributes->codes() as $code) {
            if ($this->names($code, self::CODE_WORDS)) {
                $byCode[$code] = true;
                $candidates[] = $code;
                continue;
            }
            if ($this->names($this->productAttributes->label($code), self::LABEL_WORDS)) {
                $candidates[] = $code;
            }
        }
        $root = $question->getRoot();
        $found = $this->attributeAnswers->answers(
            $candidates,
            $storeId,
            static fn (string $code): string => '{{' . $root . '.' . $code . '}}',
            static fn (string $code, string $label): string => (string) __('%1 · attribute %2', $label, $code),
            fn (int $filled, int $total, string $code): string => isset($byCode[$code])
                ? $this->plurals->sentence(
                    $total,
                    __('Found by its code. Filled for %1 of %2 product.', $filled, $total),
                    __('Found by its code. Filled for %1 of %2 products.', $filled, $total)
                )
                : $this->plurals->sentence(
                    $total,
                    __('Found by its label. Filled for %1 of %2 product.', $filled, $total),
                    __('Found by its label. Filled for %1 of %2 products.', $filled, $total)
                )
        );
        $options = $found['options'];
        $options[] = $this->answers->leaveOut(
            self::LEAVE_OUT,
            (string) __('Leave it out'),
            (string) __('Google recommends a GTIN where a product has one.'),
            HintTone::Warn
        );

        return $this->answers->suggestions(
            $question->getCode(),
            $question->getLabel(),
            $options,
            $found['best'] ?? self::LEAVE_OUT,
            $found['total']
        );
    }

    /**
     * Whether a word of a text is one of the terms
     *
     * @param string $text
     * @param string $separator
     * @return bool
     */
    private function names(string $text, string $separator): bool
    {
        $words = preg_split($separator, mb_strtolower($text, 'UTF-8'), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_intersect($words, $this->terms) !== [];
    }
}
