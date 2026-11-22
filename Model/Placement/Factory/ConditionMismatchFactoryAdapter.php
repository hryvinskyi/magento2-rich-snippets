<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Placement\Factory;

use Hryvinskyi\RichSnippets\Api\Data\ConditionLeafInterface;
use Hryvinskyi\RichSnippets\Api\Data\ConditionMismatchInterface;
use Hryvinskyi\RichSnippets\Api\Data\ConditionMismatchInterfaceFactory;
use Hryvinskyi\RichSnippets\Model\Placement\ConditionMismatchFactoryInterface;

/**
 * Delegates to the generated factory of the condition mismatch contract
 */
class ConditionMismatchFactoryAdapter implements ConditionMismatchFactoryInterface
{
    /**
     * @param ConditionMismatchInterfaceFactory $generated
     */
    public function __construct(private readonly ConditionMismatchInterfaceFactory $generated)
    {
    }

    /**
     * @inheritDoc
     */
    public function create(
        ConditionLeafInterface $condition,
        string $requirement,
        string $fieldLabel,
        string $operatorLabel,
        array $valueLabels,
        ?string $pageValue
    ): ConditionMismatchInterface {
        return $this->generated->create([
            'condition' => $condition,
            'requirement' => $requirement,
            'fieldLabel' => $fieldLabel,
            'operatorLabel' => $operatorLabel,
            'valueLabels' => $valueLabels,
            'pageValue' => $pageValue,
        ]);
    }
}
