<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Config\Source;

use Hryvinskyi\RichSnippets\Api\Data\OutputFormat;
use Magento\Framework\Data\OptionSourceInterface;
use Magento\Framework\Phrase;

/**
 * How the JSON of a script block is laid out, as options of a setting
 */
class Format implements OptionSourceInterface
{
    /**
     * @inheritDoc
     *
     * @return list<array{value: string, label: Phrase}>
     */
    public function toOptionArray(): array
    {
        $options = [];
        foreach (OutputFormat::cases() as $format) {
            $options[] = ['value' => $format->value, 'label' => $this->label($format)];
        }

        return $options;
    }

    /**
     * What the person configuring reads for each value
     *
     * @param OutputFormat $format
     * @return Phrase
     */
    private function label(OutputFormat $format): Phrase
    {
        return match ($format) {
            OutputFormat::Compact => __('Compact'),
            OutputFormat::Readable => __('Readable'),
        };
    }
}
