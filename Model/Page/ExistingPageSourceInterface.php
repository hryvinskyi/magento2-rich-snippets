<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Page;

use Magento\Framework\DB\Select;

/**
 * The pages of one kind a store view has, as a select of the entity IDs they show
 */
interface ExistingPageSourceInterface
{
    /**
     * A select of the kind's pages in the store view, one row per page
     *
     * The entity ID is in the column ExistingPagesInterface::ENTITY_ID.
     *
     * @param int $storeId
     * @return Select
     */
    public function select(int $storeId): Select;
}
