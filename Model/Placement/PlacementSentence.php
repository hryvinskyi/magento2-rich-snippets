<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Placement;

use Hryvinskyi\AdminUi\Api\Page\PluralPhrasesInterface;
use Hryvinskyi\RichSnippets\Api\ConditionFieldPoolInterface;
use Hryvinskyi\RichSnippets\Api\ConditionSentenceInterface;
use Hryvinskyi\RichSnippets\Api\Data\ConditionTreeInterface;
use Hryvinskyi\RichSnippets\Api\Data\PlacementCountInterface;
use Hryvinskyi\RichSnippets\Api\Data\SnippetInterface;
use Hryvinskyi\RichSnippets\Api\PageKindPoolInterface;
use Hryvinskyi\RichSnippets\Model\Wording\WordList;
use Magento\Store\Model\StoreManagerInterface;

/**
 * The whole placement in words, with its count, as the Pages section reads it under the builder
 *
 * - The sentence: "Output nowhere yet." with no kind of page, and "Output nowhere: it is set for no store view." when
 *   no store view it is set for exists (a store view the shop no longer has is never named, nor taken for every
 *   store view); otherwise "Output on {kinds}{where}, {views}.", where
 *   the rule follows "where" itself when there are fewer than three complete conditions, no nested group and one kind
 *   of page, and ", where the conditions are met" stands for it in every other case with a complete condition; the
 *   views are "in every store view" or "in {views} only". Each of the six shapes is one sentence, so a language
 *   orders the whole as it needs; the kinds, the rule and the store views' names are filled in.
 * - The count after it: "146 of 184 product pages match." with complete conditions ("… pages match." for several
 *   kinds, in the plural form the admin's language gives the total), "184 product pages." without; nothing for no
 *   kind, for every other page, or for the home page alone.
 * - The count line of the builder's preview: the same without the full stop, only with complete conditions.
 * - The asked notice, when conditions narrow some of several kinds and leave others: "A condition is asked only of
 *   the pages it is about. CMS pages are not narrowed by these conditions."
 *
 * The home page counts as a page that always matches. The kinds are named in their configured order.
 *
 * @phpstan-type Words array{sentence: string, count: string, countLine: string, asked: string|null}
 */
class PlacementSentence
{
    private const SHORT_RULE_LIMIT = 3;
    private const ALL_PAGES = 'all';
    private const RULE = 'rule';
    private const CONDITIONS_MET = 'met';
    private const EVERY_VIEW = 'every';
    private const SOME_VIEWS = 'only';

    /**
     * @param ConditionSentenceInterface $rule
     * @param ConditionFieldPoolInterface $fields
     * @param PageKindPoolInterface $kinds
     * @param KindPhrases $phrases
     * @param WordList $words
     * @param StoreManagerInterface $storeManager
     * @param PluralPhrasesInterface $plurals
     * @param list<string> $unlistedKinds kinds whose pages cannot be counted, so no count is said with them
     */
    public function __construct(
        private readonly ConditionSentenceInterface $rule,
        private readonly ConditionFieldPoolInterface $fields,
        private readonly PageKindPoolInterface $kinds,
        private readonly KindPhrases $phrases,
        private readonly WordList $words,
        private readonly StoreManagerInterface $storeManager,
        private readonly PluralPhrasesInterface $plurals,
        private readonly array $unlistedKinds = ['other']
    ) {
    }

    /**
     * The placement in words
     *
     * @param list<string> $kinds
     * @param ConditionTreeInterface $conditions
     * @param list<int> $storeIds
     * @param array<string,PlacementCountInterface> $counts by kind
     * @param int $labelStoreId the store view whose labels the rule uses
     * @return Words
     */
    public function describe(
        array $kinds,
        ConditionTreeInterface $conditions,
        array $storeIds,
        array $counts,
        int $labelStoreId
    ): array {
        $picked = array_values(array_intersect($this->kinds->getCodes(), $kinds));
        if ($picked === []) {
            return [
                'sentence' => (string) __('Output nowhere yet.'),
                'count' => '',
                'countLine' => '',
                'asked' => null,
            ];
        }
        $views = $this->views($storeIds);
        if ($views === null) {
            return [
                'sentence' => (string) __('Output nowhere: it is set for no store view.'),
                'count' => '',
                'countLine' => '',
                'asked' => null,
            ];
        }
        $complete = count($conditions->getCompleteLeaves());
        $sentence = $this->sentence(
            $this->words->join(array_map(fn (string $kind): string => $this->phrases->phrase($kind), $picked)),
            ...[...$this->where($picked, $conditions, $complete, $labelStoreId), ...$views]
        );
        $counted = array_intersect($picked, $this->unlistedKinds) === [];
        $matching = 0;
        $total = 0;
        foreach ($picked as $kind) {
            $matching += isset($counts[$kind]) ? $counts[$kind]->getMatching() : 0;
            $total += isset($counts[$kind]) ? $counts[$kind]->getTotal() : 0;
        }
        $line = $counted && $complete > 0 ? $this->matched($picked, $matching, $total) : '';

        return [
            'sentence' => $sentence,
            'count' => $counted ? $this->count($picked, $complete, $line, $total) : '',
            'countLine' => $line,
            'asked' => $complete > 0 && count($picked) > 1 ? $this->asked($picked, $conditions) : null,
        ];
    }

    /**
     * The sentence of one shape: which pages of the kinds, and which store views
     *
     * @param string $kinds the picked kinds, joined
     * @param string $pages the shape of the pages: `all`, `rule` or `met`
     * @param string $rule the rule's words, for the `rule` shape
     * @param string $stores the shape of the store views: `every` or `only`
     * @param string $names the store views' names, for the `only` shape
     * @return string
     */
    private function sentence(string $kinds, string $pages, string $rule, string $stores, string $names): string
    {
        $sentences = [
            self::ALL_PAGES . ' ' . self::EVERY_VIEW => __('Output on %1, in every store view.', $kinds),
            self::ALL_PAGES . ' ' . self::SOME_VIEWS => __('Output on %1, in %2 only.', $kinds, $names),
            self::RULE . ' ' . self::EVERY_VIEW => __('Output on %1 where %2, in every store view.', $kinds, $rule),
            self::RULE . ' ' . self::SOME_VIEWS => __('Output on %1 where %2, in %3 only.', $kinds, $rule, $names),
            self::CONDITIONS_MET . ' ' . self::EVERY_VIEW => __(
                'Output on %1, where the conditions are met, in every store view.',
                $kinds
            ),
            self::CONDITIONS_MET . ' ' . self::SOME_VIEWS => __(
                'Output on %1, where the conditions are met, in %2 only.',
                $kinds,
                $names
            ),
        ];

        return $sentences[$pages . ' ' . $stores]->render();
    }

    /**
     * Which pages of the kinds: all of them, those the rule (given) describes, or those that meet the conditions
     *
     * @param list<string> $picked
     * @param ConditionTreeInterface $conditions
     * @param int $complete
     * @param int $labelStoreId
     * @return array{0: string, 1: string} the shape and the rule's words
     */
    private function where(array $picked, ConditionTreeInterface $conditions, int $complete, int $labelStoreId): array
    {
        if ($complete === 0) {
            return [self::ALL_PAGES, ''];
        }
        if ($complete < self::SHORT_RULE_LIMIT && !$conditions->hasNestedGroup() && count($picked) === 1) {
            return [self::RULE, $this->rule->of($conditions, $labelStoreId)];
        }

        return [self::CONDITIONS_MET, ''];
    }

    /**
     * Which store views: every one, or the named ones ("English and Deutsch"); null when none it names exists
     *
     * @param list<int> $storeIds
     * @return array{0: string, 1: string}|null the shape and the views' names
     */
    private function views(array $storeIds): ?array
    {
        if (in_array(SnippetInterface::ALL_STORES, $storeIds, true)) {
            return [self::EVERY_VIEW, ''];
        }
        $existing = [];
        foreach ($this->storeManager->getStores() as $store) {
            $existing[(int) $store->getId()] = (string) $store->getName();
        }
        $names = '';
        foreach ($storeIds as $storeId) {
            if (!isset($existing[$storeId])) {
                continue;
            }
            $names = $names === '' ? $existing[$storeId] : (string) __('%1 and %2', $names, $existing[$storeId]);
        }

        return $names === '' ? null : [self::SOME_VIEWS, $names];
    }

    /**
     * "146 of 184 product pages match", or "… pages match" for several kinds
     *
     * @param list<string> $picked
     * @param int $matching
     * @param int $total
     * @return string
     */
    private function matched(array $picked, int $matching, int $total): string
    {
        return count($picked) === 1
            ? (string) __('%1 of %2 %3 match', $matching, $total, $this->phrases->phrase($picked[0]))
            : $this->plurals->sentence(
                $total,
                __('%1 of %2 page matches', $matching, $total),
                __('%1 of %2 pages match', $matching, $total)
            );
    }

    /**
     * The count after the sentence
     *
     * @param list<string> $picked
     * @param int $complete
     * @param string $line
     * @param int $total
     * @return string
     */
    private function count(array $picked, int $complete, string $line, int $total): string
    {
        if ($complete > 0) {
            return $line . '.';
        }
        if ($total === 1) {
            return '';
        }

        return count($picked) === 1
            ? (string) __('%1 %2.', $total, $this->phrases->phrase($picked[0]))
            : $this->plurals->sentence($total, __('%1 page.', $total), __('%1 pages.', $total));
    }

    /**
     * The notice naming the picked kinds no complete condition is about; null when there are none
     *
     * @param list<string> $picked
     * @param ConditionTreeInterface $conditions
     * @return string|null
     */
    private function asked(array $picked, ConditionTreeInterface $conditions): ?string
    {
        $about = [];
        foreach ($conditions->getCompleteLeaves() as $leaf) {
            if ($this->fields->has($leaf->getField())) {
                $about[$this->fields->get($leaf->getField())->getPageKind()] = true;
            }
        }
        $free = array_values(array_filter($picked, static fn (string $kind): bool => !isset($about[$kind])));
        if ($free === []) {
            return null;
        }
        $named = $this->words->join(array_map(fn (string $kind): string => $this->phrases->phrase($kind), $free));
        $named = mb_strtoupper(mb_substr($named, 0, 1, 'UTF-8'), 'UTF-8') . mb_substr($named, 1, null, 'UTF-8');

        if (count($free) === 1 && $this->phrases->isSingular($free[0])) {
            return (string) __(
                'A condition is asked only of the pages it is about. %1 is not narrowed by these conditions.',
                $named
            );
        }

        return (string) __(
            'A condition is asked only of the pages it is about. %1 are not narrowed by these conditions.',
            $named
        );
    }
}
