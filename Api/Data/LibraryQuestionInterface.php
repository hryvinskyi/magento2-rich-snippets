<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Api\Data;

/**
 * What a library template asks when it is added, because it differs from shop to shop
 *
 * The template holds a placeholder `{{? <code> }}` where the answer goes; the suggester named by the question offers
 * the answers, each a template fragment, or "leave it out".
 *
 * @api
 */
interface LibraryQuestionInterface
{
    /**
     * The question's code, the name of its placeholder in the template (`brand`)
     *
     * @return string
     */
    public function getCode(): string;

    /**
     * The question as the add drawer labels it (`Brand`)
     *
     * @return string
     */
    public function getLabel(): string;

    /**
     * The code of the suggester that offers the answers, in the suggester pool
     *
     * @return string
     */
    public function getSuggester(): string;

    /**
     * The variable the answers read from: `product`, or the item variable of a list the placeholder sits in
     * (`variant`)
     *
     * @return string
     */
    public function getRoot(): string;
}
