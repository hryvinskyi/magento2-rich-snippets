<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Api\Data;

/**
 * What asked for a catalog check
 *
 * @api
 */
enum CheckTrigger: string
{
    /** The nightly schedule */
    case Schedule = 'schedule';
    /** Someone in the admin ("Check now") */
    case Manual = 'manual';
    /** The end of a product import */
    case Import = 'import';
}
