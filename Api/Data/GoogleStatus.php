<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Api\Data;

/**
 * What Google does with a kind of result today
 *
 * @api
 */
enum GoogleStatus: string
{
    /** Google shows the result when the page meets its requirements */
    case Ok = 'ok';

    /** Google shows no result of its own for the type; other readers may use it */
    case None = 'none';

    /** Google used to show the result and has stopped */
    case Gone = 'gone';
}
