<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Api\Data;

/**
 * One answer to a library question: a template fragment that takes the placeholder's place, or "leave it out"
 *
 * Any text from the shop that a fragment carries (a shop name, a link) is already written in the template language's
 * escapes, so it reads literally.
 *
 * @api
 */
interface AnswerOptionInterface
{
    /**
     * The answer's key, stable among the question's answers (`attribute:manufacturer`, `leave_out`)
     *
     * @return string
     */
    public function getKey(): string;

    /**
     * The answer as the select lists it (`Manufacturer · attribute manufacturer`)
     *
     * @return string
     */
    public function getLabel(): string;

    /**
     * Whether the answer leaves the property out
     *
     * The property holding the placeholder is removed, and so is an object or a list left with nothing of its own.
     *
     * @return bool
     */
    public function isLeftOut(): bool;

    /**
     * The template fragment that takes the placeholder's place; null when the answer leaves the property out
     *
     * @return mixed
     */
    public function getFragment(): mixed;

    /**
     * The line under the select when the answer is chosen
     *
     * @return string
     */
    public function getHint(): string;

    /**
     * How the hint reads
     *
     * @return HintTone
     */
    public function getTone(): HintTone;

    /**
     * How many of the store view's product pages fill the attribute the answer reads
     *
     * Null for an answer that reads no attribute.
     *
     * @return int|null
     */
    public function getFillCount(): ?int;
}
