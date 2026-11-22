<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Api\Data;

/**
 * What an import does with one snippet of the file
 *
 * @api
 */
enum ImportChoice: string
{
    /**
     * Overwrite the shop's snippet with the code: its name, template, kinds of page, conditions and origin; it keeps
     * its switch, its store views and its place in the list
     */
    case Replace = 'replace';

    /** Add the snippet beside the shop's one, named "{name} (imported)", with a code of its own, switched off */
    case KeepBoth = 'keep_both';

    /** Add a snippet the shop has none of, with the file's code, switched off */
    case Add = 'add';

    /** Leave the shop as it is */
    case Skip = 'skip';
}
