<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Snippet;

use Hryvinskyi\RichSnippets\Api\Exception\SnippetRuleException;

/**
 * Reads and writes the schema.org type of a template's root
 *
 * The type is a property of the template's root object, `@type`: a string, or a list whose first entry is the one
 * the admin shows.
 */
interface TemplateTypeInterface
{
    /**
     * Every type the template's root names, in order
     *
     * @param string $template
     * @return list<string> empty when the template is not JSON with an object root, the root has no type, or its
     *     type is neither a string nor a non-empty list of strings
     */
    public function typesOf(string $template): array;

    /**
     * The template with another type at its root
     *
     * A string type is replaced; the first entry of a list is replaced and the others are kept; a root without a
     * type gets one after its `@context`, or first. The template is written back indented, with slashes and
     * Unicode unescaped.
     *
     * @param string $template
     * @param string $type
     * @return string
     * @throws SnippetRuleException when the template is not JSON with an object root
     */
    public function withType(string $template, string $type): string;
}
