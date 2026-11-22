<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Api\Data;

/**
 * One thing on the Overview that wants fixing, with the screen that fixes it
 *
 * The title and the description are phrases to translate, with the arguments that fill their placeholders (`%1`,
 * `%2` …) in order; the arguments are already in the admin's words.
 *
 * @api
 */
interface AttentionItemInterface
{
    /**
     * What the item is about, stable while its cause stays
     *
     * Letters, digits and `-` only (`finding-merchant-listing-image`, `theme-markup`).
     *
     * @return string
     */
    public function getKey(): string;

    /**
     * How much it matters
     *
     * @return AttentionTone
     */
    public function getTone(): AttentionTone;

    /**
     * The title, as a phrase with placeholders
     *
     * @return string
     */
    public function getTitle(): string;

    /**
     * The words that fill the title's placeholders, in order
     *
     * @return list<string>
     */
    public function getTitleArguments(): array;

    /**
     * Why it matters, as a phrase with placeholders
     *
     * @return string
     */
    public function getDescription(): string;

    /**
     * The words that fill the description's placeholders, in order
     *
     * @return list<string>
     */
    public function getDescriptionArguments(): array;

    /**
     * The label of the link to the screen that fixes it, as a phrase (`See them`)
     *
     * @return string
     */
    public function getActionLabel(): string;

    /**
     * The screen that fixes it
     *
     * @return AttentionTargetInterface
     */
    public function getTarget(): AttentionTargetInterface;
}
