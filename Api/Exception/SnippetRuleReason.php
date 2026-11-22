<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Api\Exception;

/**
 * Why a snippet, or a change to it, was refused
 *
 * @api
 */
enum SnippetRuleReason: string
{
    /** The code is outside the code grammar; parameter `code` */
    case CodeInvalid = 'code_invalid';

    /** Another snippet has the code; parameter `code` */
    case CodeTaken = 'code_taken';

    /** A save gives a stored snippet another code: a code never changes; parameter `code` */
    case CodeChanged = 'code_changed';

    /** The name is empty, longer than 255 characters or holds a control character */
    case NameInvalid = 'name_invalid';

    /** The template's root has no schema.org type, or its type is neither a string nor a list of strings */
    case TypeMissing = 'type_missing';

    /** A schema.org type is outside the type grammar; parameter `type` */
    case TypeInvalid = 'type_invalid';

    /** The template is not JSON with an object at its root, so its type cannot be written */
    case TemplateNotAnObject = 'template_not_an_object';

    /** A building block cannot be switched on */
    case BlockCannotBeSwitchedOn = 'block_cannot_be_switched_on';

    /** A building block has no kinds of page, conditions or store views of its own */
    case BlockHasNoPlacement = 'block_has_no_placement';

    /** No kind of page has the code; parameter `kind` */
    case PageKindUnknown = 'page_kind_unknown';

    /** No condition field has the code; parameter `field` */
    case ConditionFieldUnknown = 'condition_field_unknown';

    /** The condition field belongs to a kind of page the snippet is not output on; parameters `field`, `kind` */
    case ConditionFieldOutsideKinds = 'condition_field_outside_kinds';

    /** The conditions are not a tree the expression builder writes, or a condition's field is empty or too long */
    case ConditionInvalid = 'condition_invalid';

    /** The condition field reads no attribute of that name, or needs one; parameters `field`, `attribute` */
    case ConditionAttributeUnknown = 'condition_attribute_unknown';

    /** The condition field does not offer the operator; parameters `field`, `operator` */
    case ConditionOperatorUnknown = 'condition_operator_unknown';

    /** Groups of conditions nest deeper than the builder allows; parameter `depth`, the deepest allowed */
    case ConditionsTooDeep = 'conditions_too_deep';

    /** The snippet names no store view */
    case StoresMissing = 'stores_missing';

    /** A store id is negative; parameter `store` */
    case StoreInvalid = 'store_invalid';

    /** The sort order is negative */
    case SortOrderInvalid = 'sort_order_invalid';

    /** The origin's code is outside the code grammar or its version is below 1 */
    case OriginInvalid = 'origin_invalid';

    /** The snippet would include itself, directly or through others; parameter `codes`, the loop joined by " → " */
    case IncludeLoop = 'include_loop';
}
