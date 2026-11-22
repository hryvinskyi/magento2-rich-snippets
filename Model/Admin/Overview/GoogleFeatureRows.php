<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Admin\Overview;

use Hryvinskyi\AdminUi\Api\Page\PluralPhrasesInterface;
use Hryvinskyi\RichSnippets\Api\Data\FeatureOverviewInterface;
use Hryvinskyi\RichSnippets\Api\Data\FeatureTallyInterface;
use Hryvinskyi\RichSnippets\Api\Data\TallyStatus;
use Hryvinskyi\RichSnippets\Model\Placement\KindPhrases;
use Hryvinskyi\RichSnippets\Model\Wording\WordList;

/**
 * Writes the rows of the Overview's "What Google can show" card, one per kind of Google result the last check counted
 *
 * A row is `{"rule", "label", "on", "met", "total", "bar", "text", "status", "statusLabel"}`: `on` names the kinds of
 * page its snippets are output on; `bar` says whether a bar of `met` over `total` is drawn, which it is when more than
 * one block was counted and Google shows the result; `status` is `met`, `partly` or `no_result`. Products are counted
 * as products when every kind of page is a product page, else as pages.
 *
 * @phpstan-type Row array{rule: string, label: string, on: string, met: int, total: int, bar: bool, text: string,
 *     status: string, statusLabel: string}
 */
class GoogleFeatureRows
{
    private const PRODUCT = 'product';

    /**
     * @param KindPhrases $kindPhrases
     * @param WordList $words
     * @param PluralPhrasesInterface $plurals
     */
    public function __construct(
        private readonly KindPhrases $kindPhrases,
        private readonly WordList $words,
        private readonly PluralPhrasesInterface $plurals
    ) {
    }

    /**
     * The rows
     *
     * @param list<FeatureOverviewInterface> $features
     * @return list<Row>
     */
    public function rows(array $features): array
    {
        return array_map(fn (FeatureOverviewInterface $feature): array => $this->row($feature), $features);
    }

    /**
     * One row
     *
     * @param FeatureOverviewInterface $feature
     * @return Row
     */
    private function row(FeatureOverviewInterface $feature): array
    {
        $tally = $feature->getTally();
        $rule = $tally->getRule();
        $status = $tally->getStatus();

        return [
            'rule' => $rule->getCode(),
            'label' => (string) __($rule->getLabel()),
            'on' => $this->on($feature->getPageKinds()),
            'met' => $tally->getMet(),
            'total' => $tally->getTotal(),
            'bar' => $status !== TallyStatus::NoResult && $tally->getTotal() > 1,
            'text' => $this->text($tally, $feature->getPageKinds()),
            'status' => $status->value,
            'statusLabel' => (string) match ($status) {
                TallyStatus::Met => __('Met'),
                TallyStatus::Partly => __('Partly'),
                TallyStatus::NoResult => __('No result'),
            },
        ];
    }

    /**
     * The kinds of page in words, starting a line: "Product pages, category pages and CMS pages"
     *
     * @param list<string> $kinds
     * @return string
     */
    private function on(array $kinds): string
    {
        $text = $this->words->join(array_map(fn (string $kind): string => $this->kindPhrases->phrase($kind), $kinds));

        return mb_strtoupper(mb_substr($text, 0, 1)) . mb_substr($text, 1);
    }

    /**
     * The line under the bar
     *
     * A result Google does not show says so; one block says what it has, or that it misses what Google requires; an
     * optional result counts the blocks that carry it; any other counts the blocks that have what Google requires.
     *
     * @param FeatureTallyInterface $tally
     * @param list<string> $kinds
     * @return string
     */
    private function text(FeatureTallyInterface $tally, array $kinds): string
    {
        $rule = $tally->getRule();
        if ($tally->getStatus() === TallyStatus::NoResult) {
            $statusText = $rule->getStatusText();

            return $statusText === '' ? (string) __('Google shows no result for it.') : (string) __($statusText);
        }
        if ($tally->getTotal() === 1) {
            return $tally->getMet() === 1
                ? (string) __($rule->getMetText())
                : (string) __('It misses what Google requires.');
        }
        $met = $tally->getMet();
        $total = $tally->getTotal();
        $products = $kinds === [self::PRODUCT];
        if ($rule->isOptional()) {
            $phrase = (string) __($rule->getPhrase());

            return $products
                ? $this->plurals->sentence(
                    $total,
                    __('%1 of %2 product carries %3; the others leave it out', $met, $total, $phrase),
                    __('%1 of %2 products carry %3; the others leave it out', $met, $total, $phrase)
                )
                : $this->plurals->sentence(
                    $total,
                    __('%1 of %2 page carries %3; the others leave it out', $met, $total, $phrase),
                    __('%1 of %2 pages carry %3; the others leave it out', $met, $total, $phrase)
                );
        }

        return $products
            ? $this->plurals->sentence(
                $total,
                __('%1 of %2 product has what Google requires', $met, $total),
                __('%1 of %2 products have what Google requires', $met, $total)
            )
            : $this->plurals->sentence(
                $total,
                __('%1 of %2 page has what Google requires', $met, $total),
                __('%1 of %2 pages have what Google requires', $met, $total)
            );
    }
}
