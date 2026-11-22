<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Api\Data;

/**
 * How the JSON of a script block is laid out
 *
 * @api
 */
enum OutputFormat: string
{
    /** No line breaks or indentation */
    case Compact = 'compact';

    /** Line breaks and indentation, for reading the page source */
    case Readable = 'readable';
}
