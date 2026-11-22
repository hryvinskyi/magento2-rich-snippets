<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Api\Data;

/**
 * What an import found wrong with one snippet of the file
 *
 * @api
 */
enum ImportProblemReason: string
{
    /** The snippet is set for a store view this shop does not have; parameter `store`, the code */
    case StoreUnknown = 'store_unknown';

    /**
     * A condition names a value this shop does not have; parameters `field`, `attribute` (the attribute the
     * condition reads, empty for a field of one attribute), `value` (the key in the file) and `label` (the label in
     * the file, empty when it carries none); a condition with several values is reported once for each value missing
     */
    case ConditionUnresolved = 'condition_unresolved';

    /** The template includes a snippet neither this shop nor the file has; parameter `code` */
    case IncludeMissing = 'include_missing';

    /** Importing the snippet would make it include itself; parameter `codes`, the loop */
    case IncludeLoop = 'include_loop';

    /** The template does not compile; parameters `path` and `error`, the first error */
    case TemplateInvalid = 'template_invalid';

    /** The snippet breaks a snippet rule; parameter `rule`, the rule's reason, and the rule's own parameters */
    case RuleBroken = 'rule_broken';
}
