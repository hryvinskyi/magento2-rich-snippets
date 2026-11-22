<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Api;

/**
 * The suggesters of library questions, by code
 *
 * @api
 */
interface QuestionSuggesterPoolInterface
{
    /**
     * Whether a suggester has the code
     *
     * @param string $code
     * @return bool
     */
    public function has(string $code): bool;

    /**
     * The suggester of a code
     *
     * @param string $code
     * @return QuestionSuggesterInterface
     * @throws \OutOfBoundsException when no suggester has the code
     */
    public function get(string $code): QuestionSuggesterInterface;
}
