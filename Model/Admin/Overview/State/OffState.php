<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Admin\Overview\State;

use Hryvinskyi\AdminUi\Api\Page\PluralPhrasesInterface;
use Hryvinskyi\RichSnippets\Api\Data\OverviewReportInterface;
use Hryvinskyi\RichSnippets\Model\Admin\Overview\ScreenLinks;
use Hryvinskyi\RichSnippets\Model\Admin\Overview\StoreNames;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;

/**
 * The words of a scope whose page snippets are all off: what is added and since when, and that the page map shows
 * what would be written
 */
class OffState implements StateWordingInterface
{
    private const SNIPPETS_ROUTE = 'hryvinskyi_richsnippets/snippets/index';

    /**
     * @param TimezoneInterface $timezone
     * @param StoreNames $stores
     * @param ScreenLinks $links
     * @param PluralPhrasesInterface $plurals
     */
    public function __construct(
        private readonly TimezoneInterface $timezone,
        private readonly StoreNames $stores,
        private readonly ScreenLinks $links,
        private readonly PluralPhrasesInterface $plurals
    ) {
    }

    /**
     * @inheritDoc
     */
    public function badge(OverviewReportInterface $report): array
    {
        return ['on' => false, 'text' => (string) __('All snippets off')];
    }

    /**
     * @inheritDoc
     */
    public function status(OverviewReportInterface $report): array
    {
        return [
            'tone' => 'off',
            'word' => (string) __('Off'),
            'note' => $this->note($report),
            'action' => ['label' => (string) __('Open Snippets')]
                + $this->links->target(self::SNIPPETS_ROUTE, [], $report->getStoreId()),
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
            'text' => (string) __(
                'Every snippet is off, so the pages carry no structured data from this module yet.'
            ),
        ];
    }

    /**
     * @inheritDoc
     */
    public function nothingToFix(): string
    {
        return (string) __('Nothing to fix while the snippets are off.');
    }

    /**
     * What is added while nothing is on, and since when: "5 snippets are added and none is switched on. Added on
     * 13 November 2026."
     *
     * @param OverviewReportInterface $report
     * @return string
     */
    private function note(OverviewReportInterface $report): string
    {
        $added = $report->getSnippetsAdded();
        $storeId = $report->getStoreId();
        if ($added === 0) {
            return $storeId === null
                ? (string) __('No snippet is set for a store view of the shop.')
                : (string) __('No snippet is set for %1.', $this->stores->name($storeId));
        }
        $note = $added === 1
            ? (string) __('1 snippet is added and it is switched off.')
            : $this->plurals->sentence(
                $added,
                __('%1 snippet is added and none is switched on.', $added),
                __('%1 snippets are added and none is switched on.', $added)
            );
        $first = $report->getFirstAddedAt();

        return $first === null ? $note : (string) __(
            '%1 Added on %2.',
            $note,
            $this->timezone->formatDate($this->timezone->date($first), \IntlDateFormatter::LONG)
        );
    }
}
