<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Api\Data;

/**
 * The answers a library question offers in a shop, with the one suggested in advance
 *
 * @api
 */
interface QuestionSuggestionsInterface
{
    /**
     * The question's code
     *
     * @return string
     */
    public function getQuestion(): string;

    /**
     * The question's label
     *
     * @return string
     */
    public function getLabel(): string;

    /**
     * The answers, in the order the select lists them; never empty
     *
     * @return list<AnswerOptionInterface>
     */
    public function getOptions(): array;

    /**
     * The key of the answer suggested in advance, one of the options'
     *
     * @return string
     */
    public function getDefault(): string;

    /**
     * How many product pages the store view has, which the fill counts are out of
     *
     * Null when no answer reads an attribute.
     *
     * @return int|null
     */
    public function getTotal(): ?int;
}
