<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Admin\Editor;

use Hryvinskyi\RichSnippets\Api\Data\DraftProblemInterface;

/**
 * Words a draft's problems for the editor
 *
 * The template's codes are the engine's (`unknown-filter`), with their parameters by name (`%filter`); the others
 * are the snippet rules' and the editor's own. A code this list does not know is worded by its part, so a code a
 * later engine adds still reads as a sentence.
 */
class ProblemPhrases
{
    /**
     * The sentence of a problem, in the admin's language
     *
     * @param DraftProblemInterface $problem
     * @return string
     */
    public function sentence(DraftProblemInterface $problem): string
    {
        $phrase = $this->phrase($problem->getCode()) ?? $this->fallback($problem->getPart());
        $params = [];
        foreach ($problem->getParams() as $name => $value) {
            $params[(string) $name] = (string) $value;
        }

        return (string) __($phrase, $params);
    }

    /**
     * The phrase of a code; null for a code the list does not know
     *
     * @param string $code
     * @return string|null
     */
    private function phrase(string $code): ?string
    {
        return match ($code) {
            'source-too-large' => 'The template is larger than %max bytes.',
            'invalid-json' => 'The template is not valid JSON.',
            'unsupported-value' => 'The template holds a value JSON cannot write.',
            'nesting-too-deep' => 'Objects and lists are nested more than %max levels deep.',
            'expression-too-deep' => 'The expression is nested more than %max levels deep.',
            'unclosed-interpolation' => 'A {{ has no closing }}.',
            'empty-expression' => 'There is nothing between {{ and }}.',
            'unexpected-character' => 'The character %character has no meaning here.',
            'unterminated-string' => 'A quoted text has no closing quote.',
            'number-too-large' => 'The number is too large.',
            'unexpected-token' => '%token cannot stand here.',
            'unexpected-end' => 'The expression ends where more is needed.',
            'expected-path-segment' => 'A field name or a number has to follow the dot.',
            'expected-filter-name' => 'A filter name has to follow the |.',
            'invalid-filter-argument' => 'A filter argument has to be a quoted text, a number or a field.',
            'chained-comparison' => 'Two comparisons cannot follow each other; put one of them in brackets.',
            'unknown-filter' => 'There is no filter called %filter.',
            'filter-arity' => 'The filter %filter takes %min to %max arguments, not %given.',
            'filter-argument-type' => 'Argument %position of the filter %filter has to be %expected.',
            'directive-sibling' => '%directive cannot stand beside %sibling.',
            'directive-requires' => '%directive needs %required beside it.',
            'directive-value-type' => '%directive needs %expected as its value.',
            'reserved-name' => '%name is kept by the template language. Choose another name.',
            'name_invalid' => 'Name the snippet in up to 255 characters, on one line.',
            'type_missing', 'type_invalid' => 'Enter a schema.org type, for example Product.',
            'template_not_an_object' => 'The template has to be a JSON object, written between { and }.',
            'include_loop' => 'This would make a loop: %codes.',
            'page_kind_unknown' => 'There is no kind of page called %kind.',
            'condition_field_unknown' => 'There is nothing called %field to test.',
            'condition_field_outside_kinds' => 'This condition tests a kind of page the snippet is not output on.',
            'condition_invalid' => 'The conditions cannot be read. Build them again in Pages.',
            'condition_attribute_unknown' => 'There is no attribute %attribute to test.',
            'condition_operator_unknown' => 'This condition cannot compare its value that way.',
            'conditions_too_deep' => 'Groups of conditions nest at most %depth deep.',
            'stores_missing' => 'Pick at least one store view.',
            'store_invalid' => 'There is no store view %store.',
            'code_taken' => 'Another snippet was saved under the same code at the same time. Save again.',
            DraftProblemInterface::NO_PAGE_KINDS => 'Pick at least one kind of page, or the snippet is output nowhere.',
            default => null,
        };
    }

    /**
     * The phrase of a code the list does not know, by the part of the draft it concerns
     *
     * @param string $part
     * @return string
     */
    private function fallback(string $part): string
    {
        return match ($part) {
            DraftProblemInterface::PART_TEMPLATE => 'The template has an error here.',
            DraftProblemInterface::PART_NAME => 'Name the snippet in up to 255 characters, on one line.',
            DraftProblemInterface::PART_TYPE => 'Enter a schema.org type, for example Product.',
            DraftProblemInterface::PART_PAGE_KINDS => 'Pick the kinds of page again.',
            DraftProblemInterface::PART_CONDITIONS => 'Pick what to test and the value.',
            DraftProblemInterface::PART_STORES => 'Pick at least one store view.',
            default => 'The snippet cannot be saved like this.',
        };
    }
}
