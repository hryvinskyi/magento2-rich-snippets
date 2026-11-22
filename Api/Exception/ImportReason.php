<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Api\Exception;

/**
 * Why an import file, or the choices made for it, was refused as a whole
 *
 * @api
 */
enum ImportReason: string
{
    /** The file is larger than 2 MB */
    case FileTooLarge = 'file_too_large';

    /** The file is not JSON */
    case NotJson = 'not_json';

    /** The file nests deeper than 64 levels */
    case TooDeep = 'too_deep';

    /** The file is JSON, but not a Rich Snippets export: it has no header or no "snippets" list */
    case NotAnExport = 'not_an_export';

    /** The export was written in a format version this module does not read; parameter `version` */
    case VersionUnsupported = 'version_unsupported';

    /** A snippet in the file lacks a value or has one of the wrong kind; parameters `index` (from 0), `field` */
    case SnippetMalformed = 'snippet_malformed';

    /** Two snippets in the file have the same code; parameter `code` */
    case CodeRepeated = 'code_repeated';

    /** A choice names a code no snippet in the file has; parameter `code` */
    case ChoiceUnknown = 'choice_unknown';

    /** A choice is not one the snippet is offered; parameters `code`, `choice` */
    case ChoiceNotOffered = 'choice_not_offered';

    /** The snippets chosen would include themselves through each other; parameter `codes`, the loop */
    case IncludeLoop = 'include_loop';
}
