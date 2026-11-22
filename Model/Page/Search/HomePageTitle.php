<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Page\Search;

use Hryvinskyi\RichSnippets\Model\Store\StoreAddresses;
use Magento\Framework\DB\Select;

/**
 * The home page, named by the title of the CMS page set as the store view's home page, at the store view's base
 * address
 */
class HomePageTitle implements PageSearchSourceInterface
{
    /**
     * @param CmsTitledPages $titles
     * @param StoreAddresses $addresses
     */
    public function __construct(
        private readonly CmsTitledPages $titles,
        private readonly StoreAddresses $addresses
    ) {
    }

    /**
     * @inheritDoc
     */
    public function names(Select $pages, string $query, int $storeId, int $limit): array
    {
        return $this->titles->names($pages, $query, $storeId, $limit);
    }

    /**
     * @inheritDoc
     */
    public function urls(array $ids, int $storeId): array
    {
        $urls = [];
        foreach ($ids as $id) {
            $urls[$id] = $this->addresses->page('', $storeId);
        }

        return $urls;
    }
}
