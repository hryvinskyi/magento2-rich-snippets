<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Api;

use Hryvinskyi\RichSnippets\Api\Data\PageEntityInterface;

/**
 * Creates the description of a storefront page
 *
 * @api
 */
interface PageEntityFactoryInterface
{
    /**
     * A page of a kind, showing an entity, in a store view
     *
     * @param string $kind the code of the page's kind
     * @param int|null $entityId the entity the page shows; null when it shows none
     * @param int $storeId
     * @return PageEntityInterface
     * @throws \InvalidArgumentException when the kind is empty, the entity ID is not positive or the store ID is
     *     negative
     */
    public function create(string $kind, ?int $entityId, int $storeId): PageEntityInterface;
}
