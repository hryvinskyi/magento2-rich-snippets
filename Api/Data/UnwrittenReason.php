<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Api\Data;

/**
 * Why a snippet set for a page is missing from the page as it was read
 *
 * @api
 */
enum UnwrittenReason: string
{
    /** Snippets are switched off for the store view in the settings */
    case SnippetsOff = 'snippets_off';

    /** Evaluated for the page, the snippet is empty, or fails, so nothing is written */
    case Empty = 'empty';

    /** The storefront writes the snippet now, but the page as read does not carry it: an older cached copy */
    case NotInPage = 'not_in_page';
}
