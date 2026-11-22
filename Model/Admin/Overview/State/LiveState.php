<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Admin\Overview\State;

use Hryvinskyi\AdminUi\Api\Page\PagePermissionsInterface;
use Hryvinskyi\AdminUi\Api\Page\PluralPhrasesInterface;
use Hryvinskyi\RichSnippets\Api\Data\OverviewReportInterface;
use Hryvinskyi\RichSnippets\Model\Admin\Overview\ScreenLinks;
use Hryvinskyi\RichSnippets\Model\Admin\Overview\StoreNames;
use Hryvinskyi\RichSnippets\Model\Wording\CountWords;

/**
 * The words of a live scope: what is on and where, and whether the last check found invalid pages
 *
 * The status word says how many pages the last check found invalid, products when every one is a product page, and
 * "Live" when it found none. The status offers "Test a page" only to an admin who may run tests.
 */
class LiveState implements StateWordingInterface
{
    private const PRODUCT = 'product';
    private const TEST_ROUTE = 'hryvinskyi_richsnippets/test/index';
    private const TEST_PERMISSION = 'test';

    /**
     * @param StoreNames $stores
     * @param ScreenLinks $links
     * @param PagePermissionsInterface $permissions what the signed-in admin may do on the module's pages
     * @param PluralPhrasesInterface $plurals
     * @param CountWords $counts
     */
    public function __construct(
        private readonly StoreNames $stores,
        private readonly ScreenLinks $links,
        private readonly PagePermissionsInterface $permissions,
        private readonly PluralPhrasesInterface $plurals,
        private readonly CountWords $counts
    ) {
    }

    /**
     * @inheritDoc
     */
    public function badge(OverviewReportInterface $report): array
    {
        $on = $report->getSnippetsOn();

        return [
            'on' => true,
            'text' => $this->plurals->sentence($on, __('%1 snippet on', $on), __('%1 snippets on', $on)),
        ];
    }

    /**
     * @inheritDoc
     */
    public function status(OverviewReportInterface $report): array
    {
        $invalid = $report->getInvalidPagesByKind();

        return [
            'tone' => array_sum($invalid) > 0 ? 'invalid' : 'ok',
            'word' => array_sum($invalid) > 0 ? $this->invalidWord($invalid) : (string) __('Live'),
            'note' => $this->note($report),
            'action' => ($this->permissions->granted()[self::TEST_PERMISSION] ?? false)
                ? ['label' => (string) __('Test a page')]
                    + $this->links->target(self::TEST_ROUTE, [], $report->getStoreId())
                : null,
        ];
    }

    /**
     * @inheritDoc
     */
    public function kindsSubtitle(): string
    {
        return (string) __('Each name is one snippet, written as one script block.');
    }

    /**
     * @inheritDoc
     */
    public function featuresSubtitle(OverviewReportInterface $report): string
    {
        return $report->getLastRun() === null
            ? (string) __('No catalog check has finished yet.')
            : (string) __('How much of the shop has what each kind of result needs.');
    }

    /**
     * @inheritDoc
     */
    public function featuresEmpty(OverviewReportInterface $report): ?array
    {
        if ($report->getLastRun() === null) {
            return [
                'title' => (string) __('No check yet'),
                'text' => (string) __(
                    'The catalog check runs every night and after an import of products, or now from Test.'
                ),
            ];
        }

        return $report->getFeatures() !== [] ? null : [
            'title' => (string) __('Nothing counted'),
            'text' => (string) __('The last check found no block that a kind of Google result is about.'),
        ];
    }

    /**
     * @inheritDoc
     */
    public function nothingToFix(): string
    {
        return (string) __(
            'Nothing. Every page meets what Google requires, no recommended value is missing, and the theme writes'
            . ' no markup of its own.'
        );
    }

    /**
     * The status word while the last check found invalid pages
     *
     * @param array<string,int> $invalid by kind of page
     * @return string
     */
    private function invalidWord(array $invalid): string
    {
        return $this->counts->invalid(array_sum($invalid), array_keys(array_filter($invalid)) === [self::PRODUCT]);
    }

    /**
     * What is on, and where: "7 snippets on 4 kinds of page, in 3 store views."
     *
     * @param OverviewReportInterface $report
     * @return string
     */
    private function note(OverviewReportInterface $report): string
    {
        $on = $report->getSnippetsOn();
        $kinds = count($report->getKindsOn());
        $stores = $report->getStoreIdsOn();

        return (string) __(
            '%1 on %2, in %3.',
            $this->plurals->sentence($on, __('%1 snippet', $on), __('%1 snippets', $on)),
            $this->plurals->sentence($kinds, __('%1 kind of page', $kinds), __('%1 kinds of page', $kinds)),
            count($stores) === 1
                ? $this->stores->name($stores[0])
                : $this->plurals->sentence(
                    count($stores),
                    __('%1 store view', count($stores)),
                    __('%1 store views', count($stores))
                )
        );
    }
}
