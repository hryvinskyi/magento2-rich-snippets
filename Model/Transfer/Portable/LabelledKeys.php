<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Transfer\Portable;

/**
 * The resolution rule every portable value shares: the exported key is kept only when it names a value with the same
 * label here; otherwise the one value with the label is taken; a label several values share, none of them the key,
 * a label no value has and a missing (empty) label resolve to nothing
 */
class LabelledKeys
{
    /**
     * This shop's key for an exported key and label
     *
     * @param string $key
     * @param string $label
     * @param array<array-key,string> $labels this shop's label of each key; a numeric key may be an integer
     * @return string|null
     */
    public function resolve(string $key, string $label, array $labels): ?string
    {
        if ($label === '') {
            return null;
        }
        if (($labels[$key] ?? null) === $label) {
            return $key;
        }
        $matching = array_keys($labels, $label, true);

        return count($matching) === 1 ? (string) $matching[0] : null;
    }
}
