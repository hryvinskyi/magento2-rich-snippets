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
 * A date as ISO 8601 (`2026-11-30`); a value that does not start with a date is nothing
 */
class DateValue implements AttributeValueResolverInterface
{
    /**
     * @inheritDoc
     */
    public function resolve(mixed $value, array $optionLabels, int $storeId): mixed
    {
        if (!is_string($value) || preg_match('/^(\d{4}-\d{2}-\d{2})/', $value, $match) !== 1) {
            return null;
        }

        return $match[1] === '0000-00-00' ? null : $match[1];
    }
}
