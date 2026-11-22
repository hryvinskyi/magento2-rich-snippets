<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Api\Data;

/**
 * How far the shop has set up its snippets, as the Overview tells it
 *
 * @api
 */
enum SetupState: string
{
    /** The shop has no page snippet yet, so nothing is output */
    case None = 'none';

    /** Page snippets are added, and none that is output on a kind of page is switched on */
    case Off = 'off';

    /** At least one page snippet with a kind of page is switched on */
    case Live = 'live';
}
