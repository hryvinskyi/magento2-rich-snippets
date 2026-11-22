<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Api\Data;

/**
 * How a group of conditions joins the children it asks: every one must hold, or one is enough
 *
 * @api
 */
enum ConditionAggregator: string
{
    /** Every asked child holds */
    case All = 'all';

    /** At least one asked child holds */
    case Any = 'any';
}
