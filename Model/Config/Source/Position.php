<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Config\Source;

use Hryvinskyi\RichSnippets\Model\Storefront\OutputPositionPool;
use Magento\Framework\Data\OptionSourceInterface;
use Magento\Framework\Phrase;

/**
 * Where the storefront writes the snippets, as options of a setting: one option per place in the position pool, in
 * its order, labelled as the pool labels it
 */
class Position implements OptionSourceInterface
{
    /**
     * @param OutputPositionPool $positions
     */
    public function __construct(private readonly OutputPositionPool $positions)
    {
    }

    /**
     * @inheritDoc
     *
     * @return list<array{value: string, label: Phrase}>
     */
    public function toOptionArray(): array
    {
        $options = [];
        foreach ($this->positions->codes() as $code) {
            $options[] = ['value' => $code, 'label' => __($this->positions->label($code))];
        }

        return $options;
    }
}
