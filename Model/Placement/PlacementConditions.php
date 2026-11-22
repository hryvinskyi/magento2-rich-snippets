<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Placement;

use Hryvinskyi\RichSnippets\Api\Data\ConditionLeafInterface;
use Hryvinskyi\RichSnippets\Api\Data\ConditionMismatchInterface;
use Hryvinskyi\RichSnippets\Api\Data\ConditionTreeInterface;
use Hryvinskyi\RichSnippets\Api\Data\PageEntityInterface;
use Hryvinskyi\RichSnippets\Model\Wording\WordList;

/**
 * Evaluates a placement's conditions on a page, explains a page that does not meet them, and turns them into SQL for
 * counting pages, by the same rules everywhere
 *
 * A page meets the conditions unless they evaluate to false on it (a tree with nothing asked of the page holds). The
 * explanation is every asked condition that does not hold on the page, in the order written.
 */
class PlacementConditions
{
    private const DESCRIPTION_SEPARATOR = ', ';

    /**
     * @param TreeEvaluation $evaluation
     * @param TreePredicate $predicates
     * @param AskedLeaves $asked
     * @param ConditionMismatchFactoryInterface $mismatches
     * @param WordList $words
     */
    public function __construct(
        private readonly TreeEvaluation $evaluation,
        private readonly TreePredicate $predicates,
        private readonly AskedLeaves $asked,
        private readonly ConditionMismatchFactoryInterface $mismatches,
        private readonly WordList $words
    ) {
    }

    /**
     * Whether a page meets the conditions
     *
     * @param ConditionTreeInterface $conditions
     * @param PageEntityInterface $page
     * @return bool
     */
    public function holds(ConditionTreeInterface $conditions, PageEntityInterface $page): bool
    {
        return $conditions->accept($this->evaluation, $page) !== false;
    }

    /**
     * The asked conditions a page does not meet, when it does not meet the placement; none when it does
     *
     * @param ConditionTreeInterface $conditions
     * @param PageEntityInterface $page
     * @return list<ConditionMismatchInterface>
     */
    public function mismatches(ConditionTreeInterface $conditions, PageEntityInterface $page): array
    {
        if ($this->holds($conditions, $page)) {
            return [];
        }
        $mismatches = [];
        foreach ($conditions->getLeaves() as $leaf) {
            if ($leaf->accept($this->evaluation, $page) === false) {
                $mismatches[] = $this->mismatch($leaf, $page);
            }
        }

        return $mismatches;
    }

    /**
     * An SQL predicate that holds for the pages of a kind meeting the conditions
     *
     * @param ConditionTreeInterface $conditions
     * @param string $kind
     * @param string $entityIdExpression the SQL expression of the page's entity ID
     * @param int $storeId
     * @return string|null null when nothing is asked of the kind, so every page meets the placement
     */
    public function predicate(
        ConditionTreeInterface $conditions,
        string $kind,
        string $entityIdExpression,
        int $storeId
    ): ?string {
        return $conditions->accept(
            $this->predicates,
            ['kind' => $kind, 'entity' => $entityIdExpression, 'store' => $storeId]
        );
    }

    /**
     * The variable paths the conditions read, for the data sources to load with the templates' paths
     *
     * @param ConditionTreeInterface $conditions
     * @return list<string>
     */
    public function variablePaths(ConditionTreeInterface $conditions): array
    {
        $paths = [];
        foreach ($conditions->getCompleteLeaves() as $leaf) {
            foreach ($this->asked->subject($leaf)?->getVariablePaths() ?? [] as $path) {
                $paths[$path] = true;
            }
        }

        return array_map('strval', array_keys($paths));
    }

    /**
     * What a page has instead of what a condition asks
     *
     * @param ConditionLeafInterface $leaf
     * @param PageEntityInterface $page
     * @return ConditionMismatchInterface
     */
    private function mismatch(ConditionLeafInterface $leaf, PageEntityInterface $page): ConditionMismatchInterface
    {
        $subject = $this->asked->subject($leaf);
        $operator = $this->asked->operator($leaf);
        $storeId = $page->getStoreId();
        $unit = $subject?->getUnit() ?? '';
        $labels = array_map(
            static fn (string $value): string => $subject?->valueLabel($value, $storeId)
                ?? ($unit === '' ? $value : $value . ' ' . $unit),
            $this->asked->values($leaf)
        );
        $phrase = $subject?->getPhrase() ?? $leaf->getField();
        $values = $this->words->either($labels);
        $own = null;
        if ($subject !== null && $subject->namesOwnValue()) {
            $ownLabels = array_map(
                static fn (string $value): string => $subject->valueLabel($value, $storeId) ?? $value,
                $subject->pageValues($page)
            );
            $own = $ownLabels === [] ? null : implode(self::DESCRIPTION_SEPARATOR, $ownLabels);
        }

        return $this->mismatches->create(
            $leaf,
            $operator?->requirement($phrase, $values) ?? $phrase . ' ' . $values,
            $subject?->getLabel() ?? $leaf->getField(),
            $operator?->getLabel() ?? $leaf->getOperator(),
            $labels,
            $own
        );
    }
}
