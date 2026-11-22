<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Wording;

/**
 * Joins words as a sentence lists them: `a`, `a and b`, `a, b and c`, or `a, b or c` for alternatives
 *
 * Every separator, the list comma included, is a translated phrase
 */
class WordList
{
    /**
     * The words joined; empty for none
     *
     * @param list<string> $words
     * @return string
     */
    public function join(array $words): string
    {
        $last = array_pop($words);
        if ($last === null) {
            return '';
        }

        return $words === [] ? $last : (string) __('%1 and %2', $this->series($words), $last);
    }

    /**
     * The words joined as alternatives; empty for none
     *
     * @param list<string> $words
     * @return string
     */
    public function either(array $words): string
    {
        $last = array_pop($words);
        if ($last === null) {
            return '';
        }

        return $words === [] ? $last : (string) __('%1 or %2', $this->series($words), $last);
    }

    /**
     * The words one after another with the list comma between them, no conjunction; empty for none
     *
     * @param list<string> $words
     * @return string
     */
    public function series(array $words): string
    {
        $first = array_shift($words);
        if ($first === null) {
            return '';
        }

        return array_reduce(
            $words,
            static fn (string $series, string $word): string => (string) __('%1, %2', $series, $word),
            $first
        );
    }
}
