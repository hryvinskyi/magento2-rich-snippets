<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Check;

/**
 * The counts over every store view that are the sum of the store views' counts
 *
 * @phpstan-import-type Aggregate from StoreTally
 */
class MergedAggregates
{
    /**
     * @var array<string, array<string, int>> by metric, then subject
     */
    private array $sums = [];

    /**
     * Add a store view's counts
     *
     * @param list<Aggregate> $rows
     * @return void
     */
    public function add(array $rows): void
    {
        foreach ($rows as $row) {
            if (!$row['metric']->isSummed()) {
                continue;
            }
            $metric = $row['metric']->value;
            $this->sums[$metric][$row['subject']] = ($this->sums[$metric][$row['subject']] ?? 0) + $row['value'];
        }
    }

    /**
     * The sums
     *
     * @return list<Aggregate>
     */
    public function rows(): array
    {
        $rows = [];
        foreach ($this->sums as $metric => $bySubject) {
            foreach ($bySubject as $subject => $value) {
                $rows[] = [
                    'metric' => AggregateMetric::from((string) $metric),
                    'subject' => (string) $subject,
                    'value' => $value,
                ];
            }
        }

        return $rows;
    }
}
