<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Placement;

use Hryvinskyi\RichSnippets\Api\Data\ConditionLeafInterface;
use Hryvinskyi\RichSnippets\Api\Data\ConditionTreeInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * The one value a placement's conditions limit the pages of a kind to, as the condition pickers name it
 *
 * The conditions limit a kind to one value when exactly one condition is asked of the kind, and it names one value
 * with an operator that keeps only the pages that have it ("Product type is Configurable", "CMS page is one of
 * Contact"). Any other conditions — none asked, several, a negation, a comparison, several values — limit the kind
 * to no one value. The value is labelled in the store view asked about, or the default store view for every store
 * view; a value the field no longer has names nothing.
 */
class LimitingValue
{
    /**
     * @param AskedLeaves $asked
     * @param StoreManagerInterface $storeManager
     * @param list<string> $keepingOperators the codes of the operators that keep only the pages that have the value
     */
    public function __construct(
        private readonly AskedLeaves $asked,
        private readonly StoreManagerInterface $storeManager,
        private readonly array $keepingOperators = ['==', '()']
    ) {
    }

    /**
     * The value the conditions limit the kind to, or null when there is no one value
     *
     * The answer holds the value's label, and whether the value is a page itself (the CMS page Contact) rather than
     * something many pages share (the product type Configurable).
     *
     * @param ConditionTreeInterface $conditions
     * @param string $kind
     * @param int|null $storeId the store view the label is read in; the default one when null
     * @return array{label: string, page: bool}|null
     */
    public function of(ConditionTreeInterface $conditions, string $kind, ?int $storeId): ?array
    {
        $asked = array_values(array_filter(
            $conditions->getCompleteLeaves(),
            fn (ConditionLeafInterface $leaf): bool => $this->asked->isAsked($leaf, $kind)
        ));
        if (count($asked) !== 1 || !in_array($asked[0]->getOperator(), $this->keepingOperators, true)) {
            return null;
        }
        $values = $this->asked->values($asked[0]);
        $subject = $this->asked->subject($asked[0]);
        if (count($values) !== 1 || $subject === null) {
            return null;
        }
        $label = $subject->valueLabel(
            $values[0],
            $storeId ?? (int) $this->storeManager->getDefaultStoreView()?->getId()
        );

        return $label === null || $label === '' ? null : ['label' => $label, 'page' => !$subject->namesOwnValue()];
    }
}
