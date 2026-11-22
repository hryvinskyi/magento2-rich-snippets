<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Api\Data;

/**
 * How many pages have what a kind of Google result needs, in one word
 *
 * @api
 */
enum TallyStatus: string
{
    /** No page misses a required property */
    case Met = 'met';

    /** Some pages miss a required property */
    case Partly = 'partly';

    /** Google shows no result for the type, whatever the pages carry */
    case NoResult = 'no_result';
}
