<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Field\Attribute;

use Hryvinskyi\RichSnippets\Api\AttributeValueResolverInterface;

/**
 * A select's value as its option's label in the store view; an option without a label is nothing
 */
class SelectValue implements AttributeValueResolverInterface
{
    /**
     * @inheritDoc
     */
    public function resolve(mixed $value, array $optionLabels, int $storeId): mixed
    {
        return is_scalar($value) && (string) $value !== '' ? ($optionLabels[(string) $value] ?? null) : null;
    }
}
