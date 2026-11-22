<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Admin\Check;

use Hryvinskyi\AdminUi\Api\Page\PluralPhrasesInterface;
use Hryvinskyi\RichSnippets\Api\Data\CheckRunInterface;
use Hryvinskyi\RichSnippets\Api\Data\CheckState;
use Hryvinskyi\RichSnippets\Api\Data\CheckStatusInterface;
use Hryvinskyi\RichSnippets\Model\Clock\ClockInterface;
use Hryvinskyi\RichSnippets\Model\Wording\CountWords;

/**
 * Writes where the catalog check stands as the Test page's "Whole catalog" mode reads it
 *
 * `{"state", "tone", "title", "note", "facts", "progress", "last", "failed"}`: `state` is `idle`, `requested` or
 * `running`; `tone` the status word's look: `invalid`, `ok`, `busy` or `none` (no check yet); `facts` the last
 * finished run's pages by kind and its store views, `[{"label", "value"}]`; `progress` is null unless a run is
 * running, else `{"checked", "total", "percent", "text", "left"}` (`left` null until it can be told); `last` is null
 * before a run finished, else `{"id", "trigger", "startedAt", "finishedAt", "durationSeconds", "pagesChecked",
 * "storeViews", "invalid", "withGap", "toast"}` with ISO 8601 times in UTC; `failed` is null unless the newest run
 * failed after it, else `{"at", "text"}`. The status says products when every invalid page is a product page, else
 * pages. While a run waits for cron the note says so.
 *
 * @phpstan-type Fact array{label: string, value: int}
 * @phpstan-type Progress array{checked: int, total: int, percent: int, text: string, left: string|null}
 * @phpstan-type Last array{id: int, trigger: string, startedAt: string|null, finishedAt: string|null,
 *     durationSeconds: int|null, pagesChecked: int, storeViews: int, invalid: int, withGap: int, toast: string}
 * @phpstan-type Answer array{state: string, tone: string, title: string, note: string, facts: list<Fact>,
 *     progress: Progress|null, last: Last|null, failed: array{at: string, text: string}|null}
 */
class CheckStatusPresenter
{
    private const PRODUCT = 'product';
    private const MINUTE = 60;
    private const PERCENT = 100;

    /**
     * @param RunTimes $times
     * @param ClockInterface $clock
     * @param PluralPhrasesInterface $plurals
     * @param CountWords $counts
     * @param array<string,string> $factLabels the label of each kind of page counted among the facts, in order
     */
    public function __construct(
        private readonly RunTimes $times,
        private readonly ClockInterface $clock,
        private readonly PluralPhrasesInterface $plurals,
        private readonly CountWords $counts,
        private readonly array $factLabels = []
    ) {
    }

    /**
     * The status as the page reads it
     *
     * @param CheckStatusInterface $status
     * @return Answer
     */
    public function present(CheckStatusInterface $status): array
    {
        $current = $status->getCurrent();
        $last = $status->getLast();
        $failed = $status->getFailed();

        return [
            'state' => $current?->getState()->value ?? 'idle',
            'tone' => $current !== null ? 'busy' : $this->tone($status),
            'title' => $current !== null ? (string) __('Checking…') : $this->title($status),
            'note' => $this->note($current, $last),
            'facts' => $last === null ? [] : $this->facts($status, $last),
            'progress' => $current?->getState() === CheckState::Running ? $this->progress($current) : null,
            'last' => $last === null ? null : $this->last($status, $last),
            'failed' => $failed === null ? null : [
                'at' => gmdate(DATE_ATOM, $failed->getFinishedAt() ?? $failed->getRequestedAt()),
                'text' => (string) __('The last check stopped before it was finished.'),
            ],
        ];
    }

    /**
     * The look of the status word when no run is pending
     *
     * @param CheckStatusInterface $status
     * @return string
     */
    private function tone(CheckStatusInterface $status): string
    {
        if ($status->getLast() === null) {
            return 'none';
        }

        return array_sum($status->getInvalidPagesByKind()) > 0 ? 'invalid' : 'ok';
    }

    /**
     * The status word when no run is pending
     *
     * @param CheckStatusInterface $status
     * @return string
     */
    private function title(CheckStatusInterface $status): string
    {
        if ($status->getLast() === null) {
            return (string) __('No check has run yet');
        }
        $invalid = $status->getInvalidPagesByKind();
        $count = array_sum($invalid);
        if ($count === 0) {
            return (string) __('Nothing found');
        }
        return $this->counts->invalid($count, array_keys(array_filter($invalid)) === [self::PRODUCT]);
    }

    /**
     * The line under the status word
     *
     * @param CheckRunInterface|null $current
     * @param CheckRunInterface|null $last
     * @return string
     */
    private function note(?CheckRunInterface $current, ?CheckRunInterface $last): string
    {
        if ($current?->getState() === CheckState::Requested) {
            return (string) __('Waiting for the next cron run. It starts within a minute when cron is running.');
        }
        if ($current !== null) {
            return (string) __('Started at %1.', $this->times->clock($current->getStartedAt() ?? $this->clock->now()));
        }
        if ($last === null) {
            return (string) __('The check runs every night and after an import of products, or now with Check now.');
        }

        return (string) __(
            'Last run %1, in %2.',
            $this->times->at($last->getStartedAt() ?? $last->getRequestedAt()),
            $this->times->duration($last->getDuration() ?? 0)
        );
    }

    /**
     * The pages of the last run by kind, and its store views
     *
     * @param CheckStatusInterface $status
     * @param CheckRunInterface $last
     * @return list<array{label: string, value: int}>
     */
    private function facts(CheckStatusInterface $status, CheckRunInterface $last): array
    {
        $pages = $status->getPagesByKind();
        $facts = [];
        foreach ($this->factLabels as $kind => $label) {
            $facts[] = ['label' => (string) __($label), 'value' => $pages[$kind] ?? 0];
        }
        $facts[] = ['label' => (string) __('Store views'), 'value' => $last->getStoreViews()];

        return $facts;
    }

    /**
     * How far a pending run got
     *
     * @param CheckRunInterface $run
     * @return array{checked: int, total: int, percent: int, text: string, left: string|null}
     */
    private function progress(CheckRunInterface $run): array
    {
        $checked = $run->getPagesChecked();
        $total = $run->getPagesTotal();

        return [
            'checked' => $checked,
            'total' => $total,
            'percent' => $total > 0 ? (int) floor($checked * self::PERCENT / $total) : 0,
            'text' => $this->plurals->sentence(
                $total,
                __('%1 of %2 page checked', $checked, $total),
                __('%1 of %2 pages checked', $checked, $total)
            ),
            'left' => $this->left($run),
        ];
    }

    /**
     * About how long a running run still takes, at the pace it has had; null until it can be told
     *
     * @param CheckRunInterface $run
     * @return string|null
     */
    private function left(CheckRunInterface $run): ?string
    {
        $started = $run->getStartedAt();
        $checked = $run->getPagesChecked();
        $elapsed = $this->clock->now() - ($started ?? 0);
        if ($started === null || $checked === 0 || $elapsed <= 0) {
            return null;
        }
        $seconds = (int) ceil(($run->getPagesTotal() - $checked) * $elapsed / $checked);

        if ($seconds < 2 * self::MINUTE) {
            $seconds = max(1, $seconds);

            return $this->plurals->sentence(
                $seconds,
                __('About %1 second left', $seconds),
                __('About %1 seconds left', $seconds)
            );
        }
        $minutes = (int) ceil($seconds / self::MINUTE);

        return $this->plurals->sentence(
            $minutes,
            __('About %1 minute left', $minutes),
            __('About %1 minutes left', $minutes)
        );
    }

    /**
     * The last finished run
     *
     * @param CheckStatusInterface $status
     * @param CheckRunInterface $last
     * @return Last
     */
    private function last(CheckStatusInterface $status, CheckRunInterface $last): array
    {
        $started = $last->getStartedAt();
        $finished = $last->getFinishedAt();

        return [
            'id' => $last->getId(),
            'trigger' => $last->getTrigger()->value,
            'startedAt' => $started === null ? null : gmdate(DATE_ATOM, $started),
            'finishedAt' => $finished === null ? null : gmdate(DATE_ATOM, $finished),
            'durationSeconds' => $last->getDuration(),
            'pagesChecked' => $last->getPagesChecked(),
            'storeViews' => $last->getStoreViews(),
            'invalid' => array_sum($status->getInvalidPagesByKind()),
            'withGap' => array_sum($status->getGapPagesByKind()),
            'toast' => (string) __(
                'The catalog was checked: %1 pages in %2 store views.',
                $last->getPagesChecked(),
                $last->getStoreViews()
            ),
        ];
    }
}
