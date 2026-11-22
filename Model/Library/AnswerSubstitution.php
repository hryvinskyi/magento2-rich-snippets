<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Library;

use Hryvinskyi\RichSnippets\Api\Data\AnswerOptionInterface;

/**
 * Puts the chosen answers into a decoded library template, node by node
 *
 * A placeholder is a string that is exactly `{{? <question code> }}`. Its node is replaced by the answer's fragment,
 * never by editing the template's text, so what a fragment carries cannot break the JSON or reach into a
 * neighbouring expression. An answer that leaves the property out removes the placeholder's key from its object, or
 * its item from its list; an object left with nothing but `@type` and `@context`, or a list left empty, by such a
 * removal goes too, up to the root, which always stays. An `@include` of a library building block is pointed at the
 * code of the shop's snippet for that block.
 */
class AnswerSubstitution
{
    private const PLACEHOLDER_PATTERN = '/\A\{\{\?\s*([a-z][a-z0-9_]*)\s*\}\}\z/';
    private const PLACEHOLDER_START = '{{?';
    private const INCLUDE_KEY = '@include';
    private const BARE_KEYS = ['@type', '@context'];

    /**
     * The template with each placeholder replaced by its question's answer
     *
     * @param array<mixed> $template
     * @param array<string,AnswerOptionInterface> $answers by question code
     * @param array<string,string> $includes the shop's snippet code by building block library code
     * @return array<mixed>
     * @throws \OutOfBoundsException when a placeholder has no answer
     */
    public function substitute(array $template, array $answers, array $includes): array
    {
        $answer = static function (string $name) use ($answers): array {
            $option = $answers[$name] ?? throw new \OutOfBoundsException(
                sprintf('The placeholder "%s" has no answer.', $name)
            );

            return $option->isLeftOut() ? [false, null] : [true, $option->getFragment()];
        };

        return $this->root($template, $answer, $includes);
    }

    /**
     * The template with every placeholder left out, as if each question were answered "leave it out"
     *
     * @param array<mixed> $template
     * @return array<mixed>
     */
    public function withoutAnswers(array $template): array
    {
        return $this->root($template, static fn (string $name): array => [false, null], []);
    }

    /**
     * The question codes of the template's placeholders, each once, in the order they are met
     *
     * @param array<mixed> $template
     * @return list<string>
     */
    public function placeholders(array $template): array
    {
        $names = [];
        array_walk_recursive($template, function (mixed $value) use (&$names): void {
            $name = is_string($value) ? $this->placeholderName($value) : null;
            if ($name !== null) {
                $names[$name] = true;
            }
        });

        return array_map('strval', array_keys($names));
    }

    /**
     * Whether a string starts like a placeholder without being one exactly
     *
     * @param string $value
     * @return bool
     */
    public function looksLikeBrokenPlaceholder(string $value): bool
    {
        return str_contains($value, self::PLACEHOLDER_START) && $this->placeholderName($value) === null;
    }

    /**
     * The question code of a placeholder; null for any other string
     *
     * @param string $value
     * @return string|null
     */
    public function placeholderName(string $value): ?string
    {
        return preg_match(self::PLACEHOLDER_PATTERN, $value, $match) === 1 ? $match[1] : null;
    }

    /**
     * The root node substituted; the root itself is never removed
     *
     * @param array<mixed> $template
     * @param callable(string):array{bool,mixed} $answer
     * @param array<string,string> $includes
     * @return array<mixed>
     */
    private function root(array $template, callable $answer, array $includes): array
    {
        [$kept, $value] = $this->node($template, $answer, $includes);

        return $kept && is_array($value) ? $value : array_intersect_key($template, array_flip(self::BARE_KEYS));
    }

    /**
     * One node substituted: whether it stays, and its value
     *
     * @param mixed $node
     * @param callable(string):array{bool,mixed} $answer
     * @param array<string,string> $includes
     * @return array{bool, mixed}
     */
    private function node(mixed $node, callable $answer, array $includes): array
    {
        if (is_string($node)) {
            $name = $this->placeholderName($node);

            return $name === null ? [true, $node] : $answer($name);
        }
        if (!is_array($node)) {
            return [true, $node];
        }

        return array_is_list($node) ? $this->list($node, $answer, $includes) : $this->object($node, $answer, $includes);
    }

    /**
     * A list substituted; it goes when a removal leaves it empty
     *
     * @param list<mixed> $items
     * @param callable(string):array{bool,mixed} $answer
     * @param array<string,string> $includes
     * @return array{bool, mixed}
     */
    private function list(array $items, callable $answer, array $includes): array
    {
        $kept = [];
        $removed = false;
        foreach ($items as $item) {
            [$stays, $value] = $this->node($item, $answer, $includes);
            if ($stays) {
                $kept[] = $value;
                continue;
            }
            $removed = true;
        }

        return [!($removed && $kept === []), $kept];
    }

    /**
     * An object substituted; it goes when a removal leaves it nothing but `@type` and `@context`
     *
     * @param array<mixed> $properties
     * @param callable(string):array{bool,mixed} $answer
     * @param array<string,string> $includes
     * @return array{bool, mixed}
     */
    private function object(array $properties, callable $answer, array $includes): array
    {
        $kept = [];
        $removed = false;
        foreach ($properties as $key => $value) {
            if ($key === self::INCLUDE_KEY && is_string($value) && isset($includes[$value])) {
                $kept[$key] = $includes[$value];
                continue;
            }
            [$stays, $substituted] = $this->node($value, $answer, $includes);
            if ($stays) {
                $kept[$key] = $substituted;
                continue;
            }
            $removed = true;
        }
        $bare = array_diff(array_map('strval', array_keys($kept)), self::BARE_KEYS) === [];

        return [!($removed && $bare), $kept];
    }
}
