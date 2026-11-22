<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Api\Data;

/**
 * How one block of output stands against one kind of Google result
 *
 * @api
 */
enum FeatureVerdict: string
{
    /** Every required property is there */
    case Met = 'met';

    /** A required property is missing */
    case NotMet = 'not_met';

    /**
     * The block does not try for the result: every required property of an optional result is missing, or the
     * object a nested result lives in is not there
     */
    case LeftOut = 'left_out';
}
