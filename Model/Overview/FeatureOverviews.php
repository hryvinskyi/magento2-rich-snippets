<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Overview;

use Hryvinskyi\RichSnippets\Api\Data\FeatureOverviewInterface;
use Hryvinskyi\RichSnippets\Api\Data\FeatureOverviewInterfaceFactory;
use Hryvinskyi\RichSnippets\Api\Data\ResultTypeRuleInterface;
use Hryvinskyi\RichSnippets\Api\Data\SnippetInterface;
use Hryvinskyi\RichSnippets\Api\PageKindPoolInterface;
use Hryvinskyi\RichSnippets\Api\ResultTypeRulePoolInterface;
use Hryvinskyi\RichSnippets\Model\Check\AggregateMetric;
use Hryvinskyi\RichSnippets\Model\Requirement\CheckFactoryInterface;

/**
 * The kinds of Google result a catalog check counted blocks for, from its counts in a scope
 *
 * A result is listed when the check counted at least one block against it, in the rules' order. Its kinds of page are
 * those of the switched-on snippets whose type the result applies to.
 */
class FeatureOverviews
{
    /**
     * @param ResultTypeRulePoolInterface $rules
     * @param CheckFactoryInterface $checks
     * @param PageKindPoolInterface $kinds
     * @param FeatureOverviewInterfaceFactory $features
     */
    public function __construct(
        private readonly ResultTypeRulePoolInterface $rules,
        private readonly CheckFactoryInterface $checks,
        private readonly PageKindPoolInterface $kinds,
        private readonly FeatureOverviewInterfaceFactory $features
    ) {
    }

    /**
     * The results the counts hold
     *
     * @param array<string,array<string,int>> $counts the check's counts in the scope, by metric, then subject
     * @param list<SnippetInterface> $switchedOn the switched-on page snippets of the scope
     * @return list<FeatureOverviewInterface>
     */
    public function from(array $counts, array $switchedOn): array
    {
        $features = [];
        foreach ($this->rules->all() as $rule) {
            $code = $rule->getCode();
            $met = $counts[AggregateMetric::FeatureMet->value][$code] ?? 0;
            $notMet = $counts[AggregateMetric::FeatureNotMet->value][$code] ?? 0;
            $leftOut = $counts[AggregateMetric::FeatureLeftOut->value][$code] ?? 0;
            if ($met + $notMet + $leftOut === 0) {
                continue;
            }
            $features[] = $this->features->create([
                'tally' => $this->checks->tally($rule, $met, $notMet, $leftOut),
                'pageKinds' => $this->kindsOf($rule, $switchedOn),
            ]);
        }

        return $features;
    }

    /**
     * The kinds of page the switched-on snippets of the result's types are set for, in the kinds' order
     *
     * @param ResultTypeRuleInterface $rule
     * @param list<SnippetInterface> $switchedOn
     * @return list<string>
     */
    private function kindsOf(ResultTypeRuleInterface $rule, array $switchedOn): array
    {
        $kinds = [];
        foreach ($switchedOn as $snippet) {
            if ($rule->anchorFor($snippet->getType()) !== null) {
                $kinds += array_fill_keys($snippet->getPageKinds(), true);
            }
        }

        return array_values(array_filter(
            $this->kinds->getCodes(),
            static fn (string $kind): bool => isset($kinds[$kind])
        ));
    }
}
