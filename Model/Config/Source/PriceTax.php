<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Config\Source;

use Hryvinskyi\RichSnippets\Api\Data\PriceTax as PriceTaxMode;
use Magento\Framework\Data\OptionSourceInterface;
use Magento\Framework\Phrase;

/**
 * Whether the prices in snippets include tax, as options of a setting
 */
class PriceTax implements OptionSourceInterface
{
    /**
     * @inheritDoc
     *
     * @return list<array{value: string, label: Phrase}>
     */
    public function toOptionArray(): array
    {
        $options = [];
        foreach (PriceTaxMode::cases() as $mode) {
            $options[] = ['value' => $mode->value, 'label' => $this->label($mode)];
        }

        return $options;
    }

    /**
     * What the person configuring reads for each value
     *
     * @param PriceTaxMode $mode
     * @return Phrase
     */
    private function label(PriceTaxMode $mode): Phrase
    {
        return match ($mode) {
            PriceTaxMode::Catalog => __('As the catalog shows them'),
            PriceTaxMode::Including => __('Including tax'),
            PriceTaxMode::Excluding => __('Excluding tax'),
        };
    }
}
