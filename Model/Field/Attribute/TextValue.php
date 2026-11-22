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
 * A text value as it is stored; an empty one, or one that is not text, is nothing
 */
class TextValue implements AttributeValueResolverInterface
{
    /**
     * @inheritDoc
     */
    public function resolve(mixed $value, array $optionLabels, int $storeId): mixed
    {
        if (!is_scalar($value)) {
            return null;
        }
        $text = (string) $value;

        return $text === '' ? null : $text;
    }
}
