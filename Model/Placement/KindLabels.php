<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Placement;

/**
 * How the admin pages show each kind of page on its own: its label ("Product pages"), the label of one of its pages
 * ("Product page"), its glyph, and — for a kind whose pages cannot be listed — the words describing them ("Cart,
 * checkout, account, search")
 *
 * The labels and descriptions are listed in the container configuration, in English, and translated when read;
 * another module adds its kinds there. A kind without a label is labelled by its code, one without a page label names
 * its pages by its label, one without a glyph gets the glyph of a web page, and one without a description has none.
 */
class KindLabels
{
    private const LABEL = 'label';
    private const PAGE = 'page';
    private const ICON = 'icon';
    private const DESCRIPTION = 'description';
    private const DEFAULT_ICON = 'web';

    /**
     * @param array<array-key,mixed> $kinds by kind code: `label`, `page`, `icon` and `description`, each a string
     */
    public function __construct(private readonly array $kinds = [])
    {
    }

    /**
     * The kind's label, translated
     *
     * @param string $kind
     * @return string
     */
    public function label(string $kind): string
    {
        $label = $this->value($kind, self::LABEL);

        return $label === '' ? $kind : (string) __($label);
    }

    /**
     * The label of one page of the kind, translated
     *
     * @param string $kind
     * @return string
     */
    public function pageLabel(string $kind): string
    {
        $page = $this->value($kind, self::PAGE);

        return $page === '' ? $this->label($kind) : (string) __($page);
    }

    /**
     * The name of the kind's glyph
     *
     * @param string $kind
     * @return string
     */
    public function icon(string $kind): string
    {
        $icon = $this->value($kind, self::ICON);

        return $icon === '' ? self::DEFAULT_ICON : $icon;
    }

    /**
     * The words describing the kind's pages, translated; empty when the kind has none
     *
     * @param string $kind
     * @return string
     */
    public function description(string $kind): string
    {
        $description = $this->value($kind, self::DESCRIPTION);

        return $description === '' ? '' : (string) __($description);
    }

    /**
     * One configured value of a kind; empty when it is not configured
     *
     * @param string $kind
     * @param string $name
     * @return string
     */
    private function value(string $kind, string $name): string
    {
        $entry = $this->kinds[$kind] ?? null;
        $value = is_array($entry) ? ($entry[$name] ?? null) : null;

        return is_string($value) ? $value : '';
    }
}
