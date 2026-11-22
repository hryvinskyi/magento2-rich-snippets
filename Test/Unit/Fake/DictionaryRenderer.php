<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Test\Unit\Fake;

use Magento\Framework\Phrase\RendererInterface;

/**
 * Renders a phrase from a dictionary keyed by the English text, then fills `%1` … `%n`; a text the dictionary lacks
 * stays as it is, as Magento's translator leaves it
 */
class DictionaryRenderer implements RendererInterface
{
    /**
     * @param array<string, string> $dictionary translations keyed by the English text
     */
    public function __construct(private readonly array $dictionary)
    {
    }

    /**
     * @inheritDoc
     *
     * @param array<mixed> $source
     * @param array<mixed> $arguments
     */
    public function render(array $source, array $arguments)
    {
        $text = end($source);
        $text = is_string($text) ? ($this->dictionary[$text] ?? $text) : '';
        $values = [];
        foreach (array_values($arguments) as $index => $argument) {
            $values['%' . ($index + 1)] = is_scalar($argument) || $argument instanceof \Stringable
                ? (string) $argument
                : '';
        }

        return strtr($text, $values);
    }
}
