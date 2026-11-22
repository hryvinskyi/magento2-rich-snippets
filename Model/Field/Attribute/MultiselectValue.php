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
 * A multiselect's values as the list of their options' labels in the store view, in stored order
 */
class MultiselectValue implements AttributeValueResolverInterface
{
    /**
     * @inheritDoc
     */
    public function resolve(mixed $value, array $optionLabels, int $storeId): mixed
    {
        if (!is_scalar($value) || (string) $value === '') {
            return null;
        }
        $labels = [];
        foreach (explode(',', (string) $value) as $option) {
            $label = $optionLabels[trim($option)] ?? null;
            if ($label !== null) {
                $labels[] = $label;
            }
        }

        return $labels === [] ? null : $labels;
    }
}
