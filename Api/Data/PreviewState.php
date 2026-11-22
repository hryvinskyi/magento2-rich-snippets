<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Api\Data;

/**
 * What a draft gives on a sample page
 *
 * @api
 */
enum PreviewState: string
{
    /** The draft writes a block on the page */
    case Output = 'output';

    /** The template has no schema.org type at its root, so there is nothing to show yet */
    case NoType = 'no_type';

    /** The template does not compile, or the draft breaks a rule that stops it from being saved */
    case Invalid = 'invalid';

    /** The page does not meet the draft's conditions, so nothing is written there */
    case ConditionsNotMet = 'conditions_not_met';

    /** Every property is empty on the page, so the whole snippet is left out */
    case Empty = 'empty';
}
