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
 * The storefront page the current request renders
 *
 * @api
 */
interface PageContextInterface
{
    /**
     * The current request's page
     *
     * Its kind comes from the full action name, the entity from the request, the store view is the current one.
     *
     * @return PageEntityInterface
     */
    public function current(): PageEntityInterface;
}
