<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Library\Question;

use Hryvinskyi\AdminUi\Api\Page\PluralPhrasesInterface;
use Hryvinskyi\JsonTemplate\Api\TemplateLiteralsInterface;
use Hryvinskyi\RichSnippets\Api\Data\HintTone;
use Hryvinskyi\RichSnippets\Api\Data\LibraryQuestionInterface;
use Hryvinskyi\RichSnippets\Api\Data\QuestionSuggestionsInterface;
use Hryvinskyi\RichSnippets\Api\QuestionSuggesterInterface;

/**
 * Where a product's brand comes from: an attribute that holds it, falling back to the shop's name; always the shop's
 * name; or nothing
 *
 * The candidate attributes are configured in di.xml. The most filled one is suggested; when none is filled, the
 * shop's name is, and without a shop name, leaving the brand out.
 */
class BrandSuggester implements QuestionSuggesterInterface
{
    private const SHOP_NAME = 'shop_name';
    private const LEAVE_OUT = 'leave_out';

    /**
     * @param AttributeAnswers $attributeAnswers
     * @param ShopNameInterface $shopName
     * @param TemplateLiteralsInterface $literals
     * @param AnswerFactoryInterface $answers
     * @param PluralPhrasesInterface $plurals
     * @param array<string,string> $attributes the candidate attribute codes, in the order of preference
     */
    public function __construct(
        private readonly AttributeAnswers $attributeAnswers,
        private readonly ShopNameInterface $shopName,
        private readonly TemplateLiteralsInterface $literals,
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
        $shop = $this->shopName->name($storeId);
        $fallback = $shop === '' ? '' : ' | default:' . $this->literals->expressionString($shop);
        $root = $question->getRoot();
        $found = $this->attributeAnswers->answers(
            array_values($this->attributes),
            $storeId,
            static fn (string $code): string => '{{' . $root . '.' . $code . $fallback . '}}',
            static fn (string $code, string $label): string => (string) __('%1 · attribute %2', $label, $code),
            fn (int $filled, int $total): string => $shop !== '' && $filled > 0 && $filled < $total
                ? $this->plurals->sentence(
                    $total,
                    __('Filled for %1 of %2 product. The others get the shop name.', $filled, $total),
                    __('Filled for %1 of %2 products. The others get the shop name.', $filled, $total)
                )
                : $this->plurals->sentence(
                    $total,
                    __('Filled for %1 of %2 product.', $filled, $total),
                    __('Filled for %1 of %2 products.', $filled, $total)
                )
        );
        $options = $found['options'];
        if ($shop !== '') {
            $options[] = $this->answers->fragment(
                self::SHOP_NAME,
                (string) __('Always “%1”', $shop),
                $this->literals->text($shop),
                (string) __('The same on every product.'),
                HintTone::Ok
            );
        }
        $options[] = $this->answers->leaveOut(
            self::LEAVE_OUT,
            (string) __('Leave it out'),
            (string) __('Google recommends a brand.'),
            HintTone::Warn
        );

        return $this->answers->suggestions(
            $question->getCode(),
            $question->getLabel(),
            $options,
            $found['best'] ?? ($shop !== '' ? self::SHOP_NAME : self::LEAVE_OUT),
            $found['total']
        );
    }
}
