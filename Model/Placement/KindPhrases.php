<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Placement;

/**
 * How a sentence names each kind of page ("product pages", "the home page"), and whether the name is one page
 *
 * The phrases are listed in the container configuration, in English, and translated when read; another module adds
 * its kinds there. A kind without a phrase is named by its code.
 */
class KindPhrases
{
    private const PHRASE = 'phrase';
    private const SINGULAR = 'singular';

    /**
     * @param array<array-key,mixed> $kinds by kind code: `phrase` (string) and `singular` (bool)
     */
    public function __construct(private readonly array $kinds = [])
    {
    }

    /**
     * The words naming a kind of page inside a sentence
     *
     * @param string $kind
     * @return string
     */
    public function phrase(string $kind): string
    {
        $entry = $this->kinds[$kind] ?? null;
        $phrase = is_array($entry) ? ($entry[self::PHRASE] ?? null) : null;

        return is_string($phrase) ? (string) __($phrase) : $kind;
    }

    /**
     * Whether the kind's phrase names one page, so it takes "is" rather than "are"
     *
     * @param string $kind
     * @return bool
     */
    public function isSingular(string $kind): bool
    {
        $entry = $this->kinds[$kind] ?? null;

        return is_array($entry) && ($entry[self::SINGULAR] ?? false) === true;
    }
}
