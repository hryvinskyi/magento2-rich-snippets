<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Transfer;

use Magento\Framework\Filter\TranslitUrl;

/**
 * Names export files: `{name}.json` for one snippet and `rich-snippets-{shop}.json` for all, each name turned into a
 * slug the way the platform writes URL keys (lower case, every run of other characters one hyphen)
 */
class ExportFileNames
{
    private const EXTENSION = '.json';
    private const ALL_PREFIX = 'rich-snippets';
    private const FALLBACK = 'snippet';

    /**
     * @param TranslitUrl $slugs
     */
    public function __construct(private readonly TranslitUrl $slugs)
    {
    }

    /**
     * The file name of one snippet's export
     *
     * @param string $snippetName
     * @return string
     */
    public function one(string $snippetName): string
    {
        $slug = $this->slug($snippetName);

        return ($slug === '' ? self::FALLBACK : $slug) . self::EXTENSION;
    }

    /**
     * The file name of an export of every snippet
     *
     * @param string $shop the shop's name, or its host when it has none
     * @return string
     */
    public function all(string $shop): string
    {
        $slug = $this->slug($shop);

        return self::ALL_PREFIX . ($slug === '' ? '' : '-' . $slug) . self::EXTENSION;
    }

    /**
     * A name as a slug
     *
     * @param string $name
     * @return string
     */
    private function slug(string $name): string
    {
        return trim((string) $this->slugs->filter($name), '-');
    }
}
