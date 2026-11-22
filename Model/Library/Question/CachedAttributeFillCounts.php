<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Library\Question;

use Magento\Framework\Cache\FrontendInterface;

/**
 * Keeps fill counts in the module's cache type for an hour
 *
 * The counts only guide a choice in the add drawer, so a count up to an hour old is accepted; nothing cleans them
 * earlier. The key holds the store view and the attribute code, everything a count depends on.
 */
class CachedAttributeFillCounts implements AttributeFillCountsInterface
{
    private const LIFETIME = 3600;
    private const KEY_FILLED = 'rich_snippets_fill_%d_%s';
    private const KEY_TOTAL = 'rich_snippets_fill_total_%d';
    private const TAG = 'rich_snippets_fill_counts';

    /**
     * @param AttributeFillCountsInterface $inner
     * @param FrontendInterface $cache the module's cache type
     */
    public function __construct(
        private readonly AttributeFillCountsInterface $inner,
        private readonly FrontendInterface $cache
    ) {
    }

    /**
     * @inheritDoc
     */
    public function filled(string $attributeCode, int $storeId): int
    {
        return $this->remembered(
            sprintf(self::KEY_FILLED, $storeId, $attributeCode),
            fn (): int => $this->inner->filled($attributeCode, $storeId)
        );
    }

    /**
     * @inheritDoc
     */
    public function total(int $storeId): int
    {
        return $this->remembered(sprintf(self::KEY_TOTAL, $storeId), fn (): int => $this->inner->total($storeId));
    }

    /**
     * A count from the cache, or counted and kept
     *
     * @param string $key
     * @param callable():int $count
     * @return int
     */
    private function remembered(string $key, callable $count): int
    {
        $kept = $this->cache->load($key);
        if (is_string($kept) && ctype_digit($kept)) {
            return (int) $kept;
        }
        $value = $count();
        $this->cache->save((string) $value, $key, [self::TAG], self::LIFETIME);

        return $value;
    }
}
