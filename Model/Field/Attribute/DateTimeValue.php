<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Field\Attribute;

use Hryvinskyi\RichSnippets\Api\AttributeValueResolverInterface;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;

/**
 * A moment as ISO 8601 with the store view's offset (`2026-11-30T14:00:00+01:00`); values are stored in UTC
 */
class DateTimeValue implements AttributeValueResolverInterface
{
    /**
     * @param TimezoneInterface $timezone
     */
    public function __construct(private readonly TimezoneInterface $timezone)
    {
    }

    /**
     * @inheritDoc
     */
    public function resolve(mixed $value, array $optionLabels, int $storeId): mixed
    {
        if (!is_string($value) || preg_match('/^\d{4}-\d{2}-\d{2}( \d{2}:\d{2}:\d{2})?$/', $value) !== 1) {
            return null;
        }

        return $this->timezone->scopeDate($storeId, $value, true)->format(\DateTimeInterface::ATOM);
    }
}
