<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Check\Label;

/**
 * Names no page: its kind has no labels of its own, and the page is shown by its kind and ID
 */
class UnnamedPageLabels implements PageLabelsInterface
{
    /**
     * @inheritDoc
     */
    public function labels(array $ids, int $storeId): array
    {
        return [];
    }
}
