<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Admin\Overview;

use Hryvinskyi\RichSnippets\Api\Data\OutputFormat;
use Hryvinskyi\RichSnippets\Api\Data\OverviewReportInterface;
use Hryvinskyi\RichSnippets\Api\Data\ThemeMarkupState;
use Hryvinskyi\RichSnippets\Model\Admin\Check\RunTimes;
use Hryvinskyi\RichSnippets\Model\Storefront\OutputPositionPool;

/**
 * Writes the facts under the Overview's status: the pages with snippets, the last check, how the blocks are written
 * and the theme's own markup
 *
 * Each fact is `{"key", "label", "value", "tone"}`, `tone` being `warn` or null.
 *
 * @phpstan-type Fact array{key: string, label: string, value: string, tone: string|null}
 */
class StatusCard
{
    /**
     * @param RunTimes $times
     * @param OutputPositionPool $positions
     */
    public function __construct(
        private readonly RunTimes $times,
        private readonly OutputPositionPool $positions
    ) {
    }

    /**
     * The facts under the status
     *
     * @param OverviewReportInterface $report
     * @return list<Fact>
     */
    public function facts(OverviewReportInterface $report): array
    {
        $pages = $report->getPagesWithSnippets();
        $run = $report->getLastRun();
        $theme = $report->getThemeMarkup();

        return [
            [
                'key' => 'pages',
                'label' => (string) __('Pages with snippets'),
                'value' => $pages === null ? (string) __('Not checked yet') : (string) $pages,
                'tone' => null,
            ],
            [
                'key' => 'check',
                'label' => (string) __('Catalog check'),
                'value' => $run === null
                    ? (string) __('Not run yet')
                    : $this->times->moment($run->getStartedAt() ?? $run->getRequestedAt()),
                'tone' => null,
            ],
            [
                'key' => 'format',
                'label' => (string) __('Written as'),
                'value' => $this->writtenAs($report->getOutputFormat(), $report->getOutputPosition()),
                'tone' => null,
            ],
            [
                'key' => 'theme',
                'label' => (string) __('Theme’s own markup'),
                'value' => $this->themeMarkup($theme),
                'tone' => $theme === ThemeMarkupState::StillThere ? 'warn' : null,
            ],
        ];
    }

    /**
     * How the blocks are written: "Compact, in the head"
     *
     * @param OutputFormat|null $format
     * @param string|null $position the position setting's value
     * @return string
     */
    private function writtenAs(?OutputFormat $format, ?string $position): string
    {
        if ($format === null || $position === null) {
            return (string) __('Differs by store view');
        }
        $layout = match ($format) {
            OutputFormat::Compact => __('Compact'),
            OutputFormat::Readable => __('Readable'),
        };

        return (string) __('%1, %2', $layout, __($this->positions->place($position)));
    }

    /**
     * The theme's own markup in a word
     *
     * @param ThemeMarkupState $state
     * @return string
     */
    private function themeMarkup(ThemeMarkupState $state): string
    {
        return (string) match ($state) {
            ThemeMarkupState::Removed => __('Removed'),
            ThemeMarkupState::StillThere => __('Still there'),
            ThemeMarkupState::NoneSeen => __('None'),
        };
    }
}
