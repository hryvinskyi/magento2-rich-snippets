<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Check\Label;

/**
 * Names every page of a kind with one phrase, such as the home page, whatever entity it shows
 */
class FixedPageLabels implements PageLabelsInterface
{
    /**
     * @param string $phrase the name, in the module's language; translated when it is read
     */
    public function __construct(private readonly string $phrase)
    {
    }

    /**
     * @inheritDoc
     */
    public function labels(array $ids, int $storeId): array
    {
        $label = (string) __($this->phrase);

        return array_fill_keys($ids, ['label' => $label, 'sku' => null, 'group' => null]);
    }
}
