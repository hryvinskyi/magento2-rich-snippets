<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Admin\Overview;

use Hryvinskyi\RichSnippets\Api\Data\OverviewReportInterface;
use Hryvinskyi\RichSnippets\Model\Admin\Check\RunTimes;
use Hryvinskyi\RichSnippets\Model\Admin\Overview\State\StateWordings;

/**
 * Writes the Overview as its page reads it
 *
 * `{"store", "state", "badge", "status", "facts", "kinds", "features", "attention"}`: `state` is `none`, `off` or
 * `live`; `badge` `{"on", "text"}`; `status` `{"tone", "word", "note", "action"}` (see the state wordings); `facts` see
 * StatusCard; `kinds` `{"subtitle", "rows"}` (see KindRows); `features` `{"subtitle", "rows", "footer", "empty"}`
 * (see GoogleFeatureRows), `footer` saying where the numbers come from while there are rows, `empty` `{"title",
 * "text"}` in their place; `attention` `{"count", "items", "none"}` (see AttentionRows), `none` saying that nothing
 * wants fixing when there is no item.
 *
 * @phpstan-import-type Badge from \Hryvinskyi\RichSnippets\Model\Admin\Overview\State\StateWordingInterface
 * @phpstan-import-type Status from \Hryvinskyi\RichSnippets\Model\Admin\Overview\State\StateWordingInterface
 * @phpstan-import-type EmptyState from \Hryvinskyi\RichSnippets\Model\Admin\Overview\State\StateWordingInterface
 * @phpstan-import-type Fact from StatusCard
 * @phpstan-import-type Row from KindRows as KindRow
 * @phpstan-import-type Row from GoogleFeatureRows as FeatureRow
 * @phpstan-import-type Item from AttentionRows
 * @phpstan-type Answer array{store: int|null, state: string, badge: Badge, status: Status, facts: list<Fact>,
 *     kinds: array{subtitle: string, rows: list<KindRow>},
 *     features: array{subtitle: string, rows: list<FeatureRow>, footer: string|null, empty: EmptyState|null},
 *     attention: array{count: int, items: list<Item>, none: string|null}}
 */
class OverviewPresenter
{
    /**
     * @param StateWordings $states
     * @param StatusCard $statusCard
     * @param KindRows $kindRows
     * @param GoogleFeatureRows $featureRows
     * @param AttentionRows $attentionRows
     * @param RunTimes $times
     */
    public function __construct(
        private readonly StateWordings $states,
        private readonly StatusCard $statusCard,
        private readonly KindRows $kindRows,
        private readonly GoogleFeatureRows $featureRows,
        private readonly AttentionRows $attentionRows,
        private readonly RunTimes $times
    ) {
    }

    /**
     * The Overview as its page reads it
     *
     * @param OverviewReportInterface $report
     * @return Answer
     */
    public function present(OverviewReportInterface $report): array
    {
        $state = $this->states->of($report);
        $features = $this->featureRows->rows($report->getFeatures());
        $items = $this->attentionRows->rows($report->getAttentionItems());
        $run = $report->getLastRun();

        return [
            'store' => $report->getStoreId(),
            'state' => $report->getSetupState()->value,
            'badge' => $state->badge($report),
            'status' => $state->status($report),
            'facts' => $this->statusCard->facts($report),
            'kinds' => ['subtitle' => $state->kindsSubtitle(), 'rows' => $this->kindRows->rows($report)],
            'features' => [
                'subtitle' => $state->featuresSubtitle($report),
                'rows' => $features,
                'footer' => $features === [] || $run === null ? null : (string) __(
                    'From the catalog check of %1, compared with what Google publishes for each result type.'
                    . ' Google decides whether a result is shown.',
                    $this->times->at($run->getStartedAt() ?? $run->getRequestedAt())
                ),
                'empty' => $state->featuresEmpty($report),
            ],
            'attention' => [
                'count' => count($items),
                'items' => $items,
                'none' => $items === [] ? $state->nothingToFix() : null,
            ],
        ];
    }
}
