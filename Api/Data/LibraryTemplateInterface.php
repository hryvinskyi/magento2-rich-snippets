<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Api\Data;

/**
 * A ready-made snippet definition of the library: what it describes, where it is output, what Google does with it,
 * and what it asks when it is added
 *
 * Adding it makes a copy that belongs to the shop; the copy remembers the template's code and version. The template
 * holds a placeholder `{{? <question code> }}` for each question, and `@include`s only the building blocks it uses,
 * by their library codes.
 *
 * @api
 */
interface LibraryTemplateInterface
{
    /**
     * The template's code, unique in the library (`product`)
     *
     * @return string
     */
    public function getCode(): string;

    /**
     * The template's version, from 1; it grows when the library changes the template
     *
     * @return int
     */
    public function getVersion(): int;

    /**
     * The code of the library group the template is listed in
     *
     * @return string
     */
    public function getGroup(): string;

    /**
     * The template's name, which is also the name of the copy it adds
     *
     * @return string
     */
    public function getName(): string;

    /**
     * The schema.org type of the template's root (its first one when the root has a list of types)
     *
     * @return string
     */
    public function getType(): string;

    /**
     * The name of the icon the library card shows
     *
     * @return string
     */
    public function getIcon(): string;

    /**
     * What the snippet describes, in one or two sentences
     *
     * @return string
     */
    public function getDescription(): string;

    /**
     * What the snippet covers in a few words, such as `Price, stock, brand, rating of a product`
     *
     * Shown in a list of templates offered together; empty when the template has no such line.
     *
     * @return string
     */
    public function getSummary(): string;

    /**
     * Where the snippet is output, in words (`Product pages, except configurable`)
     *
     * @return string
     */
    public function getWhere(): string;

    /**
     * Whether the template adds a building block, output only inside the snippets that include it
     *
     * @return bool
     */
    public function isBlock(): bool;

    /**
     * What Google does with the snippet today
     *
     * Taken from the requirement rules of the kinds of result the template is for, when it names any.
     *
     * @return GoogleStatus
     */
    public function getGoogleStatus(): GoogleStatus;

    /**
     * The short line about Google on the library card
     *
     * @return string
     */
    public function getGoogleShort(): string;

    /**
     * The longer text about Google in the add drawer
     *
     * @return string
     */
    public function getGoogleLong(): string;

    /**
     * The codes of the requirement rules of the kinds of Google result the template is for
     *
     * @return list<string>
     */
    public function getResultTypes(): array;

    /**
     * The property names the template writes, as the library lists them
     *
     * @return list<string>
     */
    public function getProperties(): array;

    /**
     * The library codes of the building blocks the template includes
     *
     * @return list<string>
     */
    public function getUses(): array;

    /**
     * The questions asked when the template is added, in the order they are asked
     *
     * @return list<LibraryQuestionInterface>
     */
    public function getQuestions(): array;

    /**
     * The kinds of page the copy is output on; none for a building block
     *
     * @return list<string>
     */
    public function getPageKinds(): array;

    /**
     * The conditions the copy is placed with; an empty tree for a building block
     *
     * @return ConditionTreeInterface
     */
    public function getConditions(): ConditionTreeInterface;

    /**
     * The template, decoded, with the questions' placeholders in it
     *
     * @return array<mixed>
     */
    public function getTemplate(): array;
}
