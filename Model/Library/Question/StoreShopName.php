<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Library\Question;

use Hryvinskyi\RichSnippets\Api\EntityDataSourceInterface;

/**
 * Reads the shop's name from the store data source, the one the `store.name` field reads, so a suggestion names the
 * shop exactly as a snippet would
 */
class StoreShopName implements ShopNameInterface
{
    private const PART = 'information';
    private const NAME = 'name';

    /**
     * @param EntityDataSourceInterface $stores the store data source
     */
    public function __construct(private readonly EntityDataSourceInterface $stores)
    {
    }

    /**
     * @inheritDoc
     */
    public function name(int $storeId): string
    {
        $information = $this->stores->get($storeId, $storeId, self::PART);
        $name = is_array($information) ? ($information[self::NAME] ?? null) : null;

        return is_string($name) ? trim($name) : '';
    }
}
