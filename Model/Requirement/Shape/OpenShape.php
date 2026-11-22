<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Requirement\Shape;

/**
 * A node whose value is known only on a page (a whole expression, an included snippet) or of a kind not known
 * here: it writes its path, and anything below it may be written
 */
class OpenShape implements NodeShapeInterface
{
    /**
     * @inheritDoc
     */
    public function read(array $node, string $path): array
    {
        return ['marks' => [$path], 'open' => true, 'children' => [], 'text' => null];
    }
}
