<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Condition;

use Hryvinskyi\RichSnippets\Api\ConditionOperatorPoolInterface;
use Hryvinskyi\UiFormElements\Api\OperatorSetPolicyInterface;

/**
 * The operators as the expression builder lists them, `{value, label}` with `list` for one taking a list, labelled
 * by the operator pool so the builder and the sentences use the same words
 *
 * Which operators an input type offers is the builder package's operator policy; only the labels are this module's.
 */
class OperatorDescriptors
{
    /**
     * @param ConditionOperatorPoolInterface $operators
     * @param OperatorSetPolicyInterface $policy
     */
    public function __construct(
        private readonly ConditionOperatorPoolInterface $operators,
        private readonly OperatorSetPolicyInterface $policy
    ) {
    }

    /**
     * The codes the policy offers for an input type; null when it names no set for the type
     *
     * @param string $inputType
     * @return list<string>|null
     */
    public function codesFor(string $inputType): ?array
    {
        $set = $this->policy->forInputType($inputType);

        return $set === null ? null : array_column($set, 'value');
    }

    /**
     * The codes of the membership pair, `()` and `!()`
     *
     * @return list<string>
     */
    public function membershipCodes(): array
    {
        return array_column($this->policy->membership(), 'value');
    }

    /**
     * The builder's descriptors of operators, in the order given; a code the pool does not know is left out
     *
     * @param list<string> $codes
     * @return list<array{value: string, label: string, list?: true}>
     */
    public function describe(array $codes): array
    {
        $descriptors = [];
        foreach ($codes as $code) {
            if (!$this->operators->has($code)) {
                continue;
            }
            $operator = $this->operators->get($code);
            $descriptor = ['value' => $code, 'label' => $operator->getLabel()];
            if ($operator->takesList()) {
                $descriptor['list'] = true;
            }
            $descriptors[] = $descriptor;
        }

        return $descriptors;
    }

    /**
     * The builder's named operator sets, one per input type the policy knows
     *
     * @return array<string, list<array{value: string, label: string, list?: true}>>
     */
    public function sets(): array
    {
        $sets = [];
        foreach ($this->policy->sets() as $type => $set) {
            $sets[$type] = $this->describe(array_column($set, 'value'));
        }

        return $sets;
    }
}
