<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Check;

/**
 * Names a kind of gap with a key that stays the same from run to run: the result type, the property path and the
 * variables read, lower case, with every run of other characters turned into one `-`
 *
 * A whole result left out has `left-out` in place of the path. A key that would pass the stored length keeps its
 * readable start and ends in a digest of the whole, so two kinds of gap never share a key.
 */
class FindingKeys
{
    private const MAX_LENGTH = 191;
    private const KEPT_LENGTH = 170;
    private const DIGEST_LENGTH = 12;
    private const LEFT_OUT = 'left-out';

    /**
     * The key of a gap
     *
     * @param string $ruleCode
     * @param string $path empty for a whole result left out
     * @param list<string> $sources
     * @return string
     */
    public function key(string $ruleCode, string $path, array $sources): string
    {
        $named = [$ruleCode, $path === '' ? self::LEFT_OUT : $path, ...$sources];
        $parts = array_filter(
            array_map(fn (string $part): string => $this->slug($part), $named),
            static fn (string $part): bool => $part !== ''
        );
        $key = implode('-', $parts);
        if (strlen($key) <= self::MAX_LENGTH) {
            return $key;
        }

        return rtrim(substr($key, 0, self::KEPT_LENGTH), '-') . '-'
            . substr(hash('sha256', $ruleCode . "\n" . $path . "\n" . implode("\n", $sources)), 0, self::DIGEST_LENGTH);
    }

    /**
     * A text in lower case letters and digits, other characters turned into `-`
     *
     * @param string $text
     * @return string
     */
    private function slug(string $text): string
    {
        return trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower($text)), '-');
    }
}
