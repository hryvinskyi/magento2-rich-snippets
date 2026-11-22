<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Api\Data;

/**
 * Whether the theme's own Product markup is still on the product pages
 *
 * @api
 */
enum ThemeMarkupState: string
{
    /** The settings take it out, and no page test has seen it since */
    case Removed = 'removed';

    /** A page test saw it, or the settings keep it and no page test has shown the theme writes none */
    case StillThere = 'still_there';

    /** The settings keep it, and the last page test of a product page saw none */
    case NoneSeen = 'none_seen';
}
