<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Condition\Attribute\Factory;

use Hryvinskyi\RichSnippets\Api\ConditionSubjectInterface;
use Hryvinskyi\RichSnippets\Model\Condition\Attribute\ProductAttributeSubjectFactory;
use Hryvinskyi\RichSnippets\Model\Condition\Attribute\ProductAttributeSubjectFactoryInterface;

/**
 * Delegates to the generated factory of the product attribute subject
 */
class ProductAttributeSubjectFactoryAdapter implements ProductAttributeSubjectFactoryInterface
{
    /**
     * @param ProductAttributeSubjectFactory $generated
     */
    public function __construct(private readonly ProductAttributeSubjectFactory $generated)
    {
    }

    /**
     * @inheritDoc
     */
    public function create(string $code, string $label, array $operators, string $unit): ConditionSubjectInterface
    {
        return $this->generated->create([
            'code' => $code,
            'label' => $label,
            'operators' => $operators,
            'unit' => $unit,
        ]);
    }
}
