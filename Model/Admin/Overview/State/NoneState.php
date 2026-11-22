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
 * The words of a shop with no page snippet yet: nothing is output, and the page offers to start
 */
class NoneState implements StateWordingInterface
{
    /**
     * @inheritDoc
     */
    public function badge(OverviewReportInterface $report): array
    {
        return ['on' => false, 'text' => (string) __('Nothing is output')];
    }

    /**
     * @inheritDoc
     */
    public function status(OverviewReportInterface $report): array
    {
        return [
            'tone' => 'none',
            'word' => (string) __('Nothing is output yet'),
            'note' => (string) __('No snippet is added yet.'),
            'action' => null,
        ];
    }

    /**
     * @inheritDoc
     */
    public function kindsSubtitle(): string
    {
        return (string) __('What would be written once the snippets are on.');
    }

    /**
     * @inheritDoc
     */
    public function featuresSubtitle(OverviewReportInterface $report): string
    {
        return (string) __('Nothing while the snippets are off.');
    }

    /**
     * @inheritDoc
     */
    public function featuresEmpty(OverviewReportInterface $report): ?array
    {
        return [
            'title' => (string) __('Nothing to show yet'),
            'text' => (string) __('No snippet is added, so the pages carry no structured data from this module yet.'),
        ];
    }

    /**
     * @inheritDoc
     */
    public function nothingToFix(): string
    {
        return (string) __('Nothing yet.');
    }
}
