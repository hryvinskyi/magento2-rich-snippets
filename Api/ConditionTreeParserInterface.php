<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Api;

use Hryvinskyi\RichSnippets\Api\Data\ConditionTreeInterface;
use Hryvinskyi\RichSnippets\Api\Exception\SnippetRuleException;

/**
 * Reads the conditions the expression builder writes: one JSON text of a rule-shaped tree, or an empty text for none
 *
 * A group is `{"type": "rich_snippets/combine", "aggregator": "all"|"any", "conditions": [...]}`; a condition is
 * `{"type": <field code>, "attribute", "operator", "value", "is_value_processed"}`. The parser refuses, as a whole, a
 * text that is not such a tree: a node whose type is neither the group type nor a field's code, a condition whose
 * field reads no attribute of that name or does not offer its operator, groups nested deeper than GROUP_DEPTH, or
 * nodes nested deeper than the builder's validator allows. A condition without a value is kept, as incomplete.
 *
 * @api
 */
interface ConditionTreeParserInterface
{
    /**
     * How many groups may nest, the outermost included: the depth the builder lets an admin author
     */
    public const GROUP_DEPTH = 5;

    /**
     * The conditions a stored or posted text holds
     *
     * @param string $json the builder's value; empty for no conditions
     * @return ConditionTreeInterface
     * @throws SnippetRuleException
     */
    public function parse(string $json): ConditionTreeInterface;

    /**
     * The conditions an already decoded tree holds, as a library definition or an import file carries them
     *
     * @param array<mixed> $tree the decoded tree; empty for no conditions
     * @return ConditionTreeInterface
     * @throws SnippetRuleException
     */
    public function parseDecoded(array $tree): ConditionTreeInterface;

    /**
     * The conditions a stored text holds, as they were written
     *
     * A field, attribute or operator the shop no longer has does not make the text unreadable: its condition is kept
     * as written, and a page never meets it, so the snippet stays readable, listable and editable after an attribute
     * is deleted. Saving it again is refused until the condition is changed. Only a text that is no tree is refused.
     *
     * @param string $json the stored value; empty for no conditions
     * @return ConditionTreeInterface
     * @throws SnippetRuleException
     */
    public function restore(string $json): ConditionTreeInterface;
}
