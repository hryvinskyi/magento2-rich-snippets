<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Api;

use Hryvinskyi\RichSnippets\Api\Data\FieldInterface;

/**
 * Creates the fields a field group lists
 *
 * @api
 */
interface FieldFactoryInterface
{
    /**
     * A field
     *
     * @param string $path
     * @param string $label
     * @param string $groupCode
     * @param string $groupLabel
     * @param string|null $attributeCode
     * @param list<string> $pageKinds
     * @return FieldInterface
     */
    public function create(
        string $path,
        string $label,
        string $groupCode,
        string $groupLabel,
        ?string $attributeCode = null,
        array $pageKinds = []
    ): FieldInterface;
}
