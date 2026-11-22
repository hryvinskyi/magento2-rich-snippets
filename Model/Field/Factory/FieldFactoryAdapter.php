<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Field\Factory;

use Hryvinskyi\RichSnippets\Api\Data\FieldInterface;
use Hryvinskyi\RichSnippets\Api\Data\FieldInterfaceFactory;
use Hryvinskyi\RichSnippets\Api\FieldFactoryInterface;

/**
 * Creates fields through the factory Magento generates for the field contract
 */
class FieldFactoryAdapter implements FieldFactoryInterface
{
    /**
     * @param FieldInterfaceFactory $generated
     */
    public function __construct(private readonly FieldInterfaceFactory $generated)
    {
    }

    /**
     * @inheritDoc
     */
    public function create(
        string $path,
        string $label,
        string $groupCode,
        string $groupLabel,
        ?string $attributeCode = null,
        array $pageKinds = []
    ): FieldInterface {
        return $this->generated->create([
            'path' => $path,
            'label' => $label,
            'groupCode' => $groupCode,
            'groupLabel' => $groupLabel,
            'attributeCode' => $attributeCode,
            'pageKinds' => $pageKinds,
        ]);
    }
}
