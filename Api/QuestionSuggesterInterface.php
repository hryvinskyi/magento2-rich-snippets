<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Api;

use Hryvinskyi\RichSnippets\Api\Data\LibraryQuestionInterface;
use Hryvinskyi\RichSnippets\Api\Data\QuestionSuggestionsInterface;

/**
 * Offers the answers to one kind of library question in a shop, suggesting one from the shop's data
 *
 * A module adds a kind of question by adding a suggester to the suggester pool's `suggesters` argument in its
 * di.xml, under the code library templates name in a question's `suggester`. The answers are offered again when a
 * template is added, and the chosen one is taken from them by its key, so a fragment never comes from the browser.
 *
 * @api
 */
interface QuestionSuggesterInterface
{
    /**
     * The answers to a question in a store view
     *
     * @param LibraryQuestionInterface $question
     * @param int $storeId
     * @return QuestionSuggestionsInterface
     */
    public function suggest(LibraryQuestionInterface $question, int $storeId): QuestionSuggestionsInterface;
}
