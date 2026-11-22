<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Api;

use Magento\Framework\App\RequestInterface;

/**
 * One kind of page: the storefront actions it owns and the entity a request of it shows
 *
 * The kinds are exclusive: a full action name (`catalog_product_view`) belongs to one kind at most. A kind is
 * registered in the page kind pool under its code; another module adds a kind by adding an item there.
 *
 * @api
 */
interface PageKindResolverInterface
{
    /**
     * The full action names of the requests that are pages of this kind
     *
     * @return list<string>
     */
    public function getActionNames(): array;

    /**
     * The ID of the entity a request of this kind shows
     *
     * @param RequestInterface $request a request whose full action name this kind owns
     * @param int $storeId the store view the request is served in
     * @return int|null null when the request shows no entity
     */
    public function entityId(RequestInterface $request, int $storeId): ?int;
}
