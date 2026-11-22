<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Admin\Settings;

use Hryvinskyi\RichSnippets\Api\CatalogCheckInterface;
use Hryvinskyi\RichSnippets\Model\Admin\Check\RunTimes;

/**
 * The settings page's read-only "Last Check": the counts of the last finished catalog check, when it ran and how
 * long it took, and whether another one is under way
 *
 * @phpstan-type Summary array{pagesChecked: int, invalid: int, withGap: int, when: string, running: string|null}
 */
class LastCheckSummary
{
    /**
     * @param CatalogCheckInterface $catalogCheck
     * @param RunTimes $times
     */
    public function __construct(
        private readonly CatalogCheckInterface $catalogCheck,
        private readonly RunTimes $times
    ) {
    }

    /**
     * The summary; null before a check has finished
     *
     * @return Summary|null
     */
    public function summary(): ?array
    {
        $status = $this->catalogCheck->status();
        $last = $status->getLast();
        if ($last === null) {
            return null;
        }

        return [
            'pagesChecked' => $last->getPagesChecked(),
            'invalid' => array_sum($status->getInvalidPagesByKind()),
            'withGap' => array_sum($status->getGapPagesByKind()),
            'when' => (string) __(
                '%1, in %2.',
                $this->times->at($last->getStartedAt() ?? $last->getRequestedAt(), true),
                $this->times->duration($last->getDuration() ?? 0)
            ),
            'running' => $status->getCurrent() === null ? null : (string) __('Another check is under way.'),
        ];
    }
}
