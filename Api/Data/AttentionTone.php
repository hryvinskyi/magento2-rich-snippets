<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Api\Data;

/**
 * How much a thing that wants fixing matters
 *
 * @api
 */
enum AttentionTone: string
{
    /** Pages are reported as invalid because of it */
    case Bad = 'bad';

    /** Something recommended is missing, or two things disagree */
    case Warn = 'warn';

    /** Worth knowing; nothing is wrong */
    case Info = 'info';
}
