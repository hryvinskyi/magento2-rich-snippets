<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Api\Data;

/**
 * Where a catalog check is in its life
 *
 * A run is requested, then claimed by the job that runs checks, which leaves it done or failed. A requested or
 * running run is pending: there is never more than one.
 *
 * @api
 */
enum CheckState: string
{
    /** Asked for and waiting for the job that runs checks */
    case Requested = 'requested';
    /** Being run */
    case Running = 'running';
    /** Every page was checked; its findings and counts are complete */
    case Done = 'done';
    /** Stopped before every page was checked: it failed, or stopped reporting progress */
    case Failed = 'failed';
}
