<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Transfer;

use Hryvinskyi\RichSnippets\Api\Data\SnippetInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Writes a snippet's store views by code, which another shop can resolve, and resolves codes to this shop's store
 * views
 *
 * Every store view is written as `*`. A store view ID this shop no longer has is left out of an export.
 */
class PortableStores
{
    /**
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(private readonly StoreManagerInterface $storeManager)
    {
    }

    /**
     * The codes of store views
     *
     * @param list<int> $storeIds
     * @return list<string>
     */
    public function codesOf(array $storeIds): array
    {
        $codes = [];
        $known = array_flip($this->idsByCode());
        foreach ($storeIds as $storeId) {
            if ($storeId === SnippetInterface::ALL_STORES) {
                return [ExportFormat::ALL_STORES];
            }
            if (isset($known[$storeId])) {
                $codes[] = $known[$storeId];
            }
        }

        return $codes;
    }

    /**
     * This shop's store views for codes, and the codes it has no store view for
     *
     * @param list<string> $codes
     * @return array{ids: list<int>, unknown: list<string>}
     */
    public function resolve(array $codes): array
    {
        $byCode = $this->idsByCode();
        $ids = [];
        $unknown = [];
        foreach ($codes as $code) {
            if ($code === ExportFormat::ALL_STORES) {
                $ids[] = SnippetInterface::ALL_STORES;
                continue;
            }
            if (isset($byCode[$code])) {
                $ids[] = $byCode[$code];
                continue;
            }
            $unknown[] = $code;
        }

        return ['ids' => array_values(array_unique($ids)), 'unknown' => $unknown];
    }

    /**
     * The store views' IDs by code, every store view but the admin's
     *
     * @return array<string, int>
     */
    private function idsByCode(): array
    {
        $ids = [];
        foreach ($this->storeManager->getStores() as $store) {
            $ids[(string) $store->getCode()] = (int) $store->getId();
        }

        return $ids;
    }
}
