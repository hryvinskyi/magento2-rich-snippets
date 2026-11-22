<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Api\Data;

/**
 * How a snippet in an import file stands against the shop's snippets, matched by code
 *
 * @api
 */
enum ImportStatus: string
{
    /** The shop has no snippet with the code */
    case New = 'new';

    /** The shop has a snippet with the code, and replacing it would change it */
    case Changed = 'changed';

    /** The shop has a snippet with the code, and replacing it would change nothing */
    case Identical = 'identical';

    /** The snippet breaks a snippet rule, or would include itself, and cannot be imported */
    case Invalid = 'invalid';
}
