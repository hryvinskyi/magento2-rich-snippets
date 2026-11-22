<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Snippet;

use Hryvinskyi\RichSnippets\Api\Exception\SnippetRuleException;
use Hryvinskyi\RichSnippets\Api\Exception\SnippetRuleReason;

/**
 * Reads and writes `@type` on a template's root object
 *
 * The template is decoded with objects kept as objects, so an empty object anywhere in it is written back as `{}`,
 * not as an empty list.
 */
class TemplateType implements TemplateTypeInterface
{
    private const TYPE_KEY = '@type';
    private const CONTEXT_KEY = '@context';
    private const MAX_DEPTH = 512;
    private const WRITE_FLAGS = JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR;

    /**
     * @inheritDoc
     */
    public function typesOf(string $template): array
    {
        $root = $this->root($template);
        $type = $root[self::TYPE_KEY] ?? null;
        if (is_string($type)) {
            return [$type];
        }
        if (!is_array($type) || $type === [] || !array_is_list($type)) {
            return [];
        }
        $types = [];
        foreach ($type as $entry) {
            if (!is_string($entry)) {
                return [];
            }
            $types[] = $entry;
        }

        return $types;
    }

    /**
     * @inheritDoc
     */
    public function withType(string $template, string $type): string
    {
        $root = $this->root($template);
        if ($root === null) {
            throw new SnippetRuleException(SnippetRuleReason::TemplateNotAnObject);
        }
        $current = $root[self::TYPE_KEY] ?? null;
        $value = is_array($current) && $current !== [] && array_is_list($current)
            ? array_replace($current, [0 => $type])
            : $type;

        if (array_key_exists(self::TYPE_KEY, $root)) {
            $root[self::TYPE_KEY] = $value;

            return json_encode($root, self::WRITE_FLAGS);
        }

        $written = [];
        if (array_key_exists(self::CONTEXT_KEY, $root)) {
            $written[self::CONTEXT_KEY] = $root[self::CONTEXT_KEY];
        }
        $written[self::TYPE_KEY] = $value;

        return json_encode($written + $root, self::WRITE_FLAGS);
    }

    /**
     * The properties of the template's root object, in their order
     *
     * @param string $template
     * @return array<array-key, mixed>|null null when the template is not JSON with an object at its root
     */
    private function root(string $template): ?array
    {
        try {
            $decoded = json_decode($template, false, self::MAX_DEPTH, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        return $decoded instanceof \stdClass ? get_object_vars($decoded) : null;
    }
}
