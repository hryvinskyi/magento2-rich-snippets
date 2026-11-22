<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Check;

/**
 * What a catalog check counts per store view, and over every store view (store view 0)
 *
 * The subject of a count says what it is about: a kind of page, a result type's code, a snippet's code, or nothing.
 * Over every store view the pages, the invalid pages and the pages with a gap count each page once; every other
 * count is the sum of the store views'.
 */
enum AggregateMetric: string
{
    /** Pages checked, by kind of page */
    case Pages = 'pages';
    /** Pages that output at least one block; no subject */
    case PagesWithSnippets = 'pages_with_snippets';
    /** Pages missing a required property, by kind of page */
    case InvalidPages = 'invalid_pages';
    /** Valid pages missing a recommended property, by kind of page */
    case GapPages = 'gap_pages';
    /** Blocks that meet a result's requirements, by result type */
    case FeatureMet = 'feature_met';
    /** Blocks that miss a required property of a result, by result type */
    case FeatureNotMet = 'feature_not_met';
    /** Blocks that leave a result out, by result type */
    case FeatureLeftOut = 'feature_left_out';
    /** Pages on which a snippet is selected after another of its type, by snippet code */
    case SecondOfType = 'second_of_type';

    /**
     * Whether the count over every store view is the sum of the store views' counts
     *
     * @return bool
     */
    public function isSummed(): bool
    {
        return match ($this) {
            self::Pages, self::InvalidPages, self::GapPages => false,
            default => true,
        };
    }
}
