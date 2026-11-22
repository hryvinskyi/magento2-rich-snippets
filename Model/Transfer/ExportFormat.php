<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Transfer;

/**
 * The names of the export document's members, shared by the export that writes it and the import that reads it
 */
class ExportFormat
{
    public const HEADER = 'hryvinskyi_rich_snippets';
    public const VERSION = 'version';
    public const CURRENT_VERSION = 1;
    public const EXPORTED_AT = 'exportedAt';
    public const SOURCE = 'source';
    public const SNIPPETS = 'snippets';

    public const CODE = 'code';
    public const NAME = 'name';
    public const TYPE = 'type';
    public const BLOCK = 'block';
    public const ACTIVE = 'active';
    public const PAGE_KINDS = 'pageKinds';
    public const CONDITIONS = 'conditions';
    public const STORES = 'stores';
    public const SORT_ORDER = 'sortOrder';
    public const ORIGIN = 'origin';
    public const UPDATED_AT = 'updatedAt';
    public const TEMPLATE = 'template';

    public const ORIGIN_CODE = 'code';
    public const ORIGIN_VERSION = 'version';

    /** The store code that stands for every store view; no store view can have it, since a code has no `*` */
    public const ALL_STORES = '*';
}
