<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Api\Data;

/**
 * One snippet of an import file as the import found it: how it stands against the shop, what can be done with it,
 * and what is wrong with it
 *
 * @api
 */
interface ImportEntryInterface
{
    /**
     * The snippet's code in the file, which it is matched by
     *
     * @return string
     */
    public function getCode(): string;

    /**
     * The snippet's name in the file
     *
     * @return string
     */
    public function getName(): string;

    /**
     * The first schema.org type of the template's root; empty when it has none
     *
     * @return string
     */
    public function getType(): string;

    /**
     * Whether the snippet is a building block
     *
     * @return bool
     */
    public function isBlock(): bool;

    /**
     * How it stands against the shop's snippet with the code
     *
     * @return ImportStatus
     */
    public function getStatus(): ImportStatus;

    /**
     * The ID of the shop's snippet with the code; null when there is none
     *
     * @return int|null
     */
    public function getExistingId(): ?int;

    /**
     * When the shop's snippet with the code was saved last (`Y-m-d H:i:s`, UTC); null when there is none
     *
     * @return string|null
     */
    public function getChangedHere(): ?string;

    /**
     * When the snippet in the file was saved last, as the file says; null when it does not say
     *
     * @return string|null
     */
    public function getChangedInFile(): ?string;

    /**
     * What can be done with it, the one offered by default first; empty when there is nothing to do
     *
     * @return list<ImportChoice>
     */
    public function getChoices(): array;

    /**
     * What is wrong with it
     *
     * @return list<ImportProblemInterface>
     */
    public function getProblems(): array;

    /**
     * The kinds of page it is set for in the file
     *
     * @return list<string>
     */
    public function getPageKinds(): array;

    /**
     * Its conditions in words, with this shop's labels ("Product type is Virtual")
     *
     * Empty when it has none, or when one cannot be resolved.
     *
     * @return string
     */
    public function getConditionSummary(): string;
}
