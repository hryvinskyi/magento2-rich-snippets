<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Api\Data;

/**
 * How much a missing property matters
 *
 * @api
 */
enum PropertyTone: string
{
    /** A required property of a result Google shows: the page is reported as invalid without it */
    case Bad = 'bad';

    /** A recommended property: the result is valid without it */
    case Warn = 'warn';

    /** Nothing to fix: a required property of an optional result, or of a result Google does not show */
    case Note = 'note';
}
