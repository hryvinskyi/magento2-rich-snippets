<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Api\Data;

/**
 * Why a page snippet is not output on a page
 *
 * The reasons are tried in the order of the cases, and the first that applies is the one given.
 *
 * @api
 */
enum ExclusionReason: string
{
    /** The snippet is switched off */
    case SwitchedOff = 'switched_off';

    /** The snippet is output in other store views; its store IDs say which */
    case OtherStores = 'other_stores';

    /** The snippet has no kind of page, so it is output nowhere */
    case NoPageKinds = 'no_page_kinds';

    /** The snippet is output on other kinds of page; its kinds say which */
    case OtherKinds = 'other_kinds';

    /** The page does not meet the snippet's conditions; the mismatches say which fail */
    case ConditionsNotMet = 'conditions_not_met';
}
