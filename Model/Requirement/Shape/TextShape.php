<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Requirement\Shape;

/**
 * A string mixing text and expressions: it writes its own path and nothing below
 */
class TextShape implements NodeShapeInterface
{
    /**
     * @inheritDoc
     */
    public function read(array $node, string $path): array
    {
        return ['marks' => [$path], 'open' => false, 'children' => [], 'text' => null];
    }
}
