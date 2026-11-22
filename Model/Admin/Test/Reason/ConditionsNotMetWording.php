<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Admin\Test\Reason;

use Hryvinskyi\RichSnippets\Api\ConditionSentenceInterface;
use Hryvinskyi\RichSnippets\Api\Data\ConditionMismatchInterface;
use Hryvinskyi\RichSnippets\Api\Data\SnippetExclusionInterface;

/**
 * The page does not meet the snippet's conditions
 *
 * When the snippet has one complete condition, the sentence says what it asks and what the page has instead: "Set
 * for product type Configurable; this one is Simple.", or "Set for the CMS page FAQ only." when the page has no value
 * of its own to name. With more conditions it quotes them all: "The conditions in Pages ask for “…”.".
 */
class ConditionsNotMetWording implements ExclusionWordingInterface
{
    /**
     * @param ConditionSentenceInterface $sentence
     */
    public function __construct(private readonly ConditionSentenceInterface $sentence)
    {
    }

    /**
     * @inheritDoc
     */
    public function text(SnippetExclusionInterface $exclusion, int $storeId): string
    {
        $conditions = $exclusion->getSnippet()->getConditions();
        $mismatches = $exclusion->getMismatches();
        if (count($conditions->getCompleteLeaves()) === 1 && count($mismatches) === 1) {
            return $this->mismatch($mismatches[0]);
        }

        return (string) __(
            'The conditions in Pages ask for “%1”.',
            $this->sentence->of($conditions, $storeId)
        );
    }

    /**
     * One condition the page does not meet
     *
     * @param ConditionMismatchInterface $mismatch
     * @return string
     */
    private function mismatch(ConditionMismatchInterface $mismatch): string
    {
        $own = $mismatch->getPageValue();

        return (string) ($own === null
            ? __('Set for %1 only.', $mismatch->getRequirement())
            : __('Set for %1; this one is %2.', $mismatch->getRequirement(), $own));
    }
}
