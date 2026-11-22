<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Admin\Overview\State;

use Hryvinskyi\RichSnippets\Api\Data\OverviewReportInterface;

/**
 * The Overview's words that depend on how far the shop has set up its snippets: the header badge, the status card,
 * the page map's subtitle, the Google card's subtitle and empty state, and what the attention list says when empty
 *
 * @phpstan-type Link array{label: string, route: string, params: array<string, string>|\stdClass}
 * @phpstan-type Badge array{on: bool, text: string}
 * @phpstan-type Status array{tone: string, word: string, note: string, action: Link|null}
 * @phpstan-type EmptyState array{title: string, text: string}
 */
interface StateWordingInterface
{
    /**
     * The header badge: whether anything is output, and what
     *
     * @param OverviewReportInterface $report
     * @return Badge
     */
    public function badge(OverviewReportInterface $report): array;

    /**
     * The status card: its look (`ok`, `invalid`, `off`, `none`), its word, the line under it and its action
     *
     * @param OverviewReportInterface $report
     * @return Status
     */
    public function status(OverviewReportInterface $report): array;

    /**
     * The line under the page map's title
     *
     * @return string
     */
    public function kindsSubtitle(): string;

    /**
     * The line under the Google card's title
     *
     * @param OverviewReportInterface $report
     * @return string
     */
    public function featuresSubtitle(OverviewReportInterface $report): string;

    /**
     * What the Google card shows in place of its rows; null when it has rows to show
     *
     * @param OverviewReportInterface $report
     * @return EmptyState|null
     */
    public function featuresEmpty(OverviewReportInterface $report): ?array;

    /**
     * What the attention list says when nothing wants fixing
     *
     * @return string
     */
    public function nothingToFix(): string;
}
