<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Api\Data;

/**
 * A storefront page as a visitor got it: where it was read and the HTML it answered
 *
 * @api
 */
interface StorefrontPageInterface
{
    /**
     * The absolute address the page was read at, after the redirects that were followed
     *
     * @return string
     */
    public function getUrl(): string;

    /**
     * The path and query of that address from the store view's base address on, starting with `/`
     *
     * @return string
     */
    public function getPath(): string;

    /**
     * The HTTP status of the answer, a success status
     *
     * @return int
     */
    public function getStatus(): int;

    /**
     * The HTML of the page
     *
     * @return string
     */
    public function getBody(): string;
}
