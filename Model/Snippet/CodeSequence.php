<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Snippet;

use Hryvinskyi\RichSnippets\Api\Data\SnippetInterface;

/**
 * Turns a slug into a code in the code grammar, and finds the first one of its sequence no snippet has
 *
 * The base is the slug cut to 64 characters without hyphens at either end; a base that does not start with a letter
 * is put behind `snippet-`, and an empty one is `snippet`. The sequence is the base, then the base with `-2`, `-3` …,
 * cut so that the code stays within 64 characters.
 */
class CodeSequence
{
    private const MAX_LENGTH = 64;
    private const FALLBACK = 'snippet';
    private const FIRST_SUFFIX = 2;

    /** Characters a numbered suffix may take from the end of the base, `-` and up to eleven digits */
    private const SUFFIX_ROOM = 12;

    /**
     * The base code of a slug
     *
     * @param string $slug lowercase letters, digits and hyphens; anything else is dropped
     * @return string
     */
    public function baseOf(string $slug): string
    {
        $base = trim((string) preg_replace(['/[^a-z0-9-]+/', '/-{2,}/'], ['', '-'], strtolower($slug)), '-');
        if ($base === '') {
            return self::FALLBACK;
        }
        if (preg_match('/\A[a-z]/', $base) !== 1 || strlen($base) < 2) {
            $base = self::FALLBACK . '-' . $base;
        }

        return rtrim(substr($base, 0, self::MAX_LENGTH), '-');
    }

    /**
     * The first code of the base's sequence that is not taken
     *
     * @param string $base a code from baseOf()
     * @param list<string> $taken the codes snippets have, at least those starting with the base's first characters
     * @return string
     */
    public function firstFree(string $base, array $taken): string
    {
        $taken = array_fill_keys($taken, true);
        if (!isset($taken[$base])) {
            return $base;
        }
        for ($number = self::FIRST_SUFFIX;; $number++) {
            $suffix = '-' . $number;
            $code = rtrim(substr($base, 0, self::MAX_LENGTH - strlen($suffix)), '-') . $suffix;
            if (!isset($taken[$code]) && preg_match(SnippetInterface::CODE_PATTERN, $code) === 1) {
                return $code;
            }
        }
    }

    /**
     * The characters every code of the base's sequence starts with
     *
     * @param string $base
     * @return string
     */
    public function prefixOf(string $base): string
    {
        return substr($base, 0, self::MAX_LENGTH - self::SUFFIX_ROOM);
    }
}
