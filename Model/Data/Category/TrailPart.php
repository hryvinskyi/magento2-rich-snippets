<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Data\Category;

use Hryvinskyi\RichSnippets\Api\EntityDataPartInterface;
use Hryvinskyi\RichSnippets\Model\Breadcrumb\CategoryTrails;

/**
 * Each category's breadcrumb levels: the path to it in the store view's tree, itself last
 */
class TrailPart implements EntityDataPartInterface
{
    /**
     * @param CategoryTrails $trails
     */
    public function __construct(private readonly CategoryTrails $trails)
    {
    }

    /**
     * @inheritDoc
     */
    public function load(array $ids, int $storeId, array $variablePaths, array $collectionLimits): array
    {
        return $this->trails->trails($ids, $storeId);
    }
}
