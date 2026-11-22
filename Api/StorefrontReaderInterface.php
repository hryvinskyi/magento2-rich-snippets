<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Api;

use Hryvinskyi\RichSnippets\Api\Data\StorefrontPageInterface;
use Hryvinskyi\RichSnippets\Api\Exception\StorefrontReadException;

/**
 * Reads a page of a store view's storefront over HTTP as a first-time visitor's browser gets it, page cache included
 *
 * The address is always the store view's own base address followed by a path, so no caller chooses the host. A
 * redirect is followed only to an address of the same scheme, host and port as the store view's base address, and
 * only a few times; the read has a connection time limit, one deadline for the whole read and a size limit.
 *
 * @api
 */
interface StorefrontReaderInterface
{
    /**
     * Read the page at a path of a store view
     *
     * @param int $storeId
     * @param string $path the path from the store view's base address on (see StorefrontPathInterface)
     * @return StorefrontPageInterface
     * @throws \InvalidArgumentException when the path is not a storefront path
     * @throws StorefrontReadException when the page cannot be read or the storefront answers other than success
     */
    public function read(int $storeId, string $path): StorefrontPageInterface;
}
