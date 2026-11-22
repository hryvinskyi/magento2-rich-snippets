<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Api;

use Hryvinskyi\RichSnippets\Api\Data\StructuredDataInterface;

/**
 * Finds the structured data in the HTML of a page
 *
 * A JSON-LD block is a script element of the `application/ld+json` media type, attributed to the snippet its
 * `data-snippet` attribute names; its text is decoded with a depth limit and never trusted as anything but data.
 * Microdata is an element with `itemscope` whose `itemtype` names schema.org. HTML that is not well formed is read
 * the way a browser reads it, without failing.
 *
 * @api
 */
interface HtmlStructuredDataScannerInterface
{
    /**
     * The structured data of a page
     *
     * @param string $html
     * @return StructuredDataInterface
     */
    public function scan(string $html): StructuredDataInterface;
}
