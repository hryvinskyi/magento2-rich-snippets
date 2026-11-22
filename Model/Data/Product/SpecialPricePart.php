<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Data\Product;

use Hryvinskyi\RichSnippets\Api\EntityDataPartInterface;
use Hryvinskyi\RichSnippets\Model\Catalog\EavValueReader;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;

/**
 * The last day of each product's special price, as `special_to_date` (ISO 8601), only while the special price is active
 * today in the store view's time zone and has a last day
 */
class SpecialPricePart implements EntityDataPartInterface
{
    private const PRICE = 'special_price';
    private const FROM = 'special_from_date';
    private const TO = 'special_to_date';

    /**
     * @param EavValueReader $reader the product attribute values
     * @param TimezoneInterface $timezone
     */
    public function __construct(
        private readonly EavValueReader $reader,
        private readonly TimezoneInterface $timezone
    ) {
    }

    /**
     * @inheritDoc
     */
    public function load(array $ids, int $storeId, array $variablePaths, array $collectionLimits): array
    {
        $today = $this->timezone->scopeDate($storeId)->format('Y-m-d');
        $entries = [];
        foreach ($this->reader->values($ids, [self::PRICE, self::FROM, self::TO], $storeId) as $id => $row) {
            $from = $this->day($row[self::FROM] ?? null);
            $to = $this->day($row[self::TO] ?? null);
            $active = is_numeric($row[self::PRICE] ?? null)
                && ($from === null || $from <= $today)
                && ($to === null || $to >= $today);
            if ($active && $to !== null) {
                $entries[$id] = ['special_to_date' => $to];
            }
        }

        return $entries;
    }

    /**
     * The day a stored date stands for; null when none is stored
     *
     * @param mixed $value
     * @return string|null
     */
    private function day(mixed $value): ?string
    {
        return is_string($value) && strlen($value) >= 10 ? substr($value, 0, 10) : null;
    }
}
