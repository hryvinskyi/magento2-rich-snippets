<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Page\Existing;

use Hryvinskyi\RichSnippets\Api\ExistingPagesInterface;
use Hryvinskyi\RichSnippets\Model\Page\Cms\StoreCmsPagesInterface;
use Hryvinskyi\RichSnippets\Model\Page\ExistingPageSourceInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Select;
use Magento\Framework\DB\Sql\ExpressionFactory;

/**
 * The home page of a store view: always one page, showing the configured home CMS page, or no entity when that page
 * cannot be found
 */
class HomePage implements ExistingPageSourceInterface
{
    private const STORE_TABLE = 'store';

    /**
     * @param ResourceConnection $resource
     * @param StoreCmsPagesInterface $cmsPages
     * @param ExpressionFactory $expressions
     */
    public function __construct(
        private readonly ResourceConnection $resource,
        private readonly StoreCmsPagesInterface $cmsPages,
        private readonly ExpressionFactory $expressions
    ) {
    }

    /**
     * @inheritDoc
     */
    public function select(int $storeId): Select
    {
        $pageId = $this->cmsPages->homePageId($storeId);

        return $this->resource->getConnection()->select()
            ->from(
                ['store' => $this->resource->getTableName(self::STORE_TABLE)],
                [
                    ExistingPagesInterface::ENTITY_ID => $this->expressions->create([
                        'expression' => $pageId === null ? 'NULL' : (string) $pageId,
                    ]),
                ]
            )
            ->where('store.store_id = ?', $storeId);
    }
}
