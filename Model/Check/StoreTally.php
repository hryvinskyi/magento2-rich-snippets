<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Check;

use Hryvinskyi\RichSnippets\Api\Data\FeatureVerdict;
use Hryvinskyi\RichSnippets\Api\Data\OutputCheckInterface;
use Hryvinskyi\RichSnippets\Api\Data\PageEntityInterface;
use Hryvinskyi\RichSnippets\Api\Data\PropertyTone;
use Hryvinskyi\RichSnippets\Api\Data\SnippetInterface;

/**
 * What a catalog check found in one store view, page by page
 *
 * A page counts once in each finding it shows, however many of its blocks show it. A page is invalid when one of
 * its gaps is bad, and has a gap when it is valid and one of its gaps is a warning. Per page the selected snippets
 * are compared by type in their output order: each one after the first of its type is counted as a second one of
 * that type on the page. The pages of each finding are collected until they are taken, so a run keeps only one
 * batch of them in memory; the counts are kept for the whole store view.
 *
 * @phpstan-import-type Mark from FindingMarks
 * @phpstan-type Tally array{
 *     tone: PropertyTone,
 *     rule: string,
 *     path: string,
 *     sources: list<string>,
 *     snippets: array<string, true>,
 *     pages: int,
 *     kinds: array<string, int>
 * }
 * @phpstan-type Item array{kind: string, entity: int}
 * @phpstan-type Aggregate array{metric: AggregateMetric, subject: string, value: int}
 */
class StoreTally
{
    /**
     * @var array<string, Tally> by finding key
     */
    private array $findings = [];

    /**
     * @var array<string, list<Item>> by finding key, not taken yet
     */
    private array $items = [];

    /**
     * @var array<string, array<string, int>> by metric, then subject
     */
    private array $counts = [];

    /**
     * @param FindingMarks $marks
     * @param int $storeId
     */
    public function __construct(
        private readonly FindingMarks $marks,
        private readonly int $storeId
    ) {
    }

    /**
     * The store view counted
     *
     * @return int
     */
    public function getStoreId(): int
    {
        return $this->storeId;
    }

    /**
     * Count one checked page
     *
     * @param PageEntityInterface $page
     * @param list<SnippetInterface> $selected the snippets selected for the page, in output order
     * @param list<array{SnippetInterface,OutputCheckInterface}> $blocks each block the page output, with its check
     * @return void
     */
    public function addPage(PageEntityInterface $page, array $selected, array $blocks): void
    {
        $kind = $page->getKind();
        $this->add(AggregateMetric::Pages, $kind);
        $this->countSecondsOfType($selected);
        if ($blocks === []) {
            return;
        }
        $this->add(AggregateMetric::PagesWithSnippets, '');

        $invalid = false;
        $gap = false;
        foreach ($this->pageMarks($blocks) as $key => [$mark, $snippets]) {
            $this->record($key, $mark, $snippets, $page);
            $invalid = $invalid || $mark['tone'] === PropertyTone::Bad;
            $gap = $gap || $mark['tone'] === PropertyTone::Warn;
        }
        if ($invalid) {
            $this->add(AggregateMetric::InvalidPages, $kind);
        } elseif ($gap) {
            $this->add(AggregateMetric::GapPages, $kind);
        }
    }

    /**
     * The pages of each finding collected since they were last taken; they are forgotten
     *
     * @return array<string, list<Item>> by finding key
     */
    public function takeItems(): array
    {
        $items = $this->items;
        $this->items = [];

        return $items;
    }

    /**
     * Every finding so far, by key
     *
     * @return array<string, Tally>
     */
    public function findings(): array
    {
        return $this->findings;
    }

    /**
     * Every count so far
     *
     * @return list<Aggregate>
     */
    public function aggregates(): array
    {
        $rows = [];
        foreach ($this->counts as $metric => $bySubject) {
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

    /**
     * The gaps of a page's blocks, each once, with the snippets whose blocks show it
     *
     * @param list<array{SnippetInterface,OutputCheckInterface}> $blocks
     * @return array<string, array{Mark, array<string, true>}>
     */
    private function pageMarks(array $blocks): array
    {
        $marks = [];
        foreach ($blocks as [$snippet, $check]) {
            foreach ($check->getFeatures() as $feature) {
                $this->add($this->verdictMetric($feature->getVerdict()), $feature->getRule()->getCode());
            }
            foreach ($this->marks->of($check) as $mark) {
                $marks[$mark['key']] ??= [$mark, []];
                $marks[$mark['key']][1][$snippet->getCode()] = true;
            }
        }

        return $marks;
    }

    /**
     * Count a page in a finding and keep the page for the finding's list
     *
     * @param string $key
     * @param Mark $mark
     * @param array<string,true> $snippets
     * @param PageEntityInterface $page
     * @return void
     */
    private function record(string $key, array $mark, array $snippets, PageEntityInterface $page): void
    {
        $kind = $page->getKind();
        $tally = $this->findings[$key] ?? [
            'tone' => $mark['tone'],
            'rule' => $mark['rule'],
            'path' => $mark['path'],
            'sources' => $mark['sources'],
            'snippets' => [],
            'pages' => 0,
            'kinds' => [],
        ];
        $tally['snippets'] += $snippets;
        $tally['pages']++;
        $tally['kinds'][$kind] = ($tally['kinds'][$kind] ?? 0) + 1;
        $this->findings[$key] = $tally;
        $this->items[$key][] = ['kind' => $kind, 'entity' => $page->getEntityId() ?? 0];
    }

    /**
     * Count each selected snippet that comes after another of its type
     *
     * @param list<SnippetInterface> $selected
     * @return void
     */
    private function countSecondsOfType(array $selected): void
    {
        $types = [];
        foreach ($selected as $snippet) {
            $type = $snippet->getType();
            if (isset($types[$type])) {
                $this->add(AggregateMetric::SecondOfType, $snippet->getCode());
            }
            $types[$type] = true;
        }
    }

    /**
     * The count a block's verdict on a result goes to
     *
     * @param FeatureVerdict $verdict
     * @return AggregateMetric
     */
    private function verdictMetric(FeatureVerdict $verdict): AggregateMetric
    {
        return match ($verdict) {
            FeatureVerdict::Met => AggregateMetric::FeatureMet,
            FeatureVerdict::NotMet => AggregateMetric::FeatureNotMet,
            FeatureVerdict::LeftOut => AggregateMetric::FeatureLeftOut,
        };
    }

    /**
     * Add one to a count
     *
     * @param AggregateMetric $metric
     * @param string $subject
     * @return void
     */
    private function add(AggregateMetric $metric, string $subject): void
    {
        $this->counts[$metric->value][$subject] = ($this->counts[$metric->value][$subject] ?? 0) + 1;
    }
}
