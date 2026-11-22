<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Api\Data;

/**
 * How the hint under a suggested answer reads: a fine choice, or one to think twice about
 *
 * @api
 */
enum HintTone: string
{
    /** The answer serves the shop well */
    case Ok = 'ok';

    /** The answer leaves something out, or few products fill it */
    case Warn = 'warn';
}
