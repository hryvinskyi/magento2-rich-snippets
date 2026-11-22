<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Admin;

/**
 * The module's admin permissions, as its endpoints require them
 *
 * Seeing the module reads everything: the pages, the lists, the catalogs, the checks. Changing snippets covers
 * every write to a snippet, exports and imports included. Running tests covers the page test and asking for a
 * catalog check. Changing the settings opens the module's section of the store configuration.
 */
class AdminResources
{
    public const VIEW = 'Hryvinskyi_RichSnippets::view';
    public const SNIPPETS = 'Hryvinskyi_RichSnippets::snippets';
    public const TEST = 'Hryvinskyi_RichSnippets::test';
    public const CONFIG = 'Hryvinskyi_RichSnippets::config';
}
