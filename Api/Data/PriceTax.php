<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Api\Data;

/**
 * Whether the prices a snippet writes include tax
 *
 * @api
 */
enum PriceTax: string
{
    /** As the catalog displays prices; a catalog that displays both writes them including tax */
    case Catalog = 'catalog';

    /** Including tax */
    case Including = 'incl';

    /** Excluding tax */
    case Excluding = 'excl';
}
