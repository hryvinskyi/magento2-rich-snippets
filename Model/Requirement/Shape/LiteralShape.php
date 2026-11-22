<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Requirement\Shape;

use Hryvinskyi\JsonTemplate\Api\Data\AstNodeKind;

/**
 * A constant JSON value: an empty value writes nothing, an object its keys, a list its items at its own path
 */
class LiteralShape implements NodeShapeInterface
{
    /**
     * @inheritDoc
     */
    public function read(array $node, string $path): array
    {
        $value = $node['value'] ?? null;
        if ($value === null || $value === '' || $value === []) {
            return ['marks' => [], 'open' => false, 'children' => [], 'text' => null];
        }
        if (!is_array($value)) {
            $text = is_string($value) ? $value : null;

            return ['marks' => [$path], 'open' => false, 'children' => [], 'text' => $text];
        }
        $isList = array_is_list($value);
        $children = [];
        foreach ($value as $key => $item) {
            $children[] = [
                [AstNodeKind::KEY => AstNodeKind::LITERAL, 'value' => $item],
                $isList ? $path : ($path === '' ? (string) $key : $path . '.' . $key),
            ];
        }

        return ['marks' => [$path], 'open' => false, 'children' => $children, 'text' => null];
    }
}
