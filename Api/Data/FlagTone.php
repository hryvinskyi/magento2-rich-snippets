<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Api\Data;

/**
 * How much a flag on a snippet's row matters
 *
 * @api
 */
enum FlagTone: string
{
    /** Something Google needs is missing: the snippet cannot give the result it is for */
    case Bad = 'bad';

    /** Something to look at: the snippet works, but not as well as it could */
    case Warn = 'warn';

    /** Worth knowing; nothing has to be fixed */
    case Note = 'note';
}
