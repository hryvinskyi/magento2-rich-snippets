<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Api;

use Hryvinskyi\JsonTemplate\Api\Exception\CompileException;
use Hryvinskyi\RichSnippets\Api\Data\AddedSnippetInterface;
use Hryvinskyi\RichSnippets\Api\Data\LibraryEntryInterface;
use Hryvinskyi\RichSnippets\Api\Data\QuestionSuggestionsInterface;
use Hryvinskyi\RichSnippets\Api\Exception\IdempotencyException;
use Hryvinskyi\RichSnippets\Api\Exception\LibraryException;
use Hryvinskyi\RichSnippets\Api\Exception\MalformedLibraryTemplateException;
use Hryvinskyi\RichSnippets\Api\Exception\SnippetRuleException;

/**
 * The library of ready-made snippets: what it offers the shop, what each template asks, and adding copies
 *
 * Every copy arrives switched off, so adding one changes nothing on the storefront and cleans no page. A copy is
 * named after its template, with a number when a snippet has that name (`Product 2`), and remembers the template's
 * code and version. Adding a template adds the building blocks it uses that the shop has none of, and makes the
 * copy include the ones the shop has. An add carries an idempotency key, generated once per intent: a request
 * repeated with the same key creates nothing and answers what the first one did.
 *
 * Suggestions and fill counts are those of the default store view.
 *
 * @api
 */
interface LibraryInterface
{
    /**
     * Every template, with what the shop has of it
     *
     * @return list<LibraryEntryInterface>
     * @throws MalformedLibraryTemplateException
     */
    public function list(): array;

    /**
     * The codes of the recommended templates, in the order they are added
     *
     * Adding the recommended set adds those the shop has no copy of.
     *
     * @return list<string>
     */
    public function recommended(): array;

    /**
     * The answers each question of a template offers, in the order the questions are asked
     *
     * @param string $code the template's code
     * @return list<QuestionSuggestionsInterface>
     * @throws LibraryException when no template has the code
     * @throws MalformedLibraryTemplateException
     */
    public function suggestions(string $code): array;

    /**
     * Add a copy of a template, switched off, with the building blocks it uses
     *
     * @param string $code the template's code
     * @param array<string,string> $answers the chosen answer's key by question code; a question left unanswered
     *     takes the suggested answer
     * @param string $idempotencyKey
     * @return list<AddedSnippetInterface> the building blocks first, then the copy
     * @throws LibraryException when the template, a question or an answer is unknown
     * @throws IdempotencyException when the key is invalid or was used for another kind of write
     * @throws SnippetRuleException|CompileException when a copy breaks a snippet rule
     * @throws MalformedLibraryTemplateException
     */
    public function add(string $code, array $answers, string $idempotencyKey): array;

    /**
     * Add the recommended templates the shop has no copy of yet
     *
     * Each is added with its suggested answers, together with the building blocks it uses.
     *
     * @param string $idempotencyKey
     * @return list<AddedSnippetInterface> for each template, its building blocks first, then the copy
     * @throws IdempotencyException when the key is invalid or was used for another kind of write
     * @throws SnippetRuleException|CompileException when a copy breaks a snippet rule
     * @throws MalformedLibraryTemplateException
     */
    public function addRecommended(string $idempotencyKey): array;
}
