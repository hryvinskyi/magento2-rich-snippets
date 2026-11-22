<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Transfer\Portable;

use Hryvinskyi\RichSnippets\Api\PortableConditionValueInterface;
use Hryvinskyi\UiFormElements\Api\AttributeOptionsProviderInterface;

/**
 * Carries a product attribute's value between shops: an option by its admin label, since an option's ID is a row of
 * this shop; any other value as it is, since a text, a number or a day means the same everywhere
 *
 * The attribute travels by its code, which names it in every shop.
 */
class AttributeOptionLabel implements PortableConditionValueInterface
{
    /**
     * @param AttributeOptionsProviderInterface $options
     * @param LabelledKeys $keys
     */
    public function __construct(
        private readonly AttributeOptionsProviderInterface $options,
        private readonly LabelledKeys $keys
    ) {
    }

    /**
     * @inheritDoc
     */
    public function labelOf(string $key, ?string $attribute): ?string
    {
        $labels = $this->labels($attribute);

        return $labels === [] ? $key : $labels[$key] ?? null;
    }

    /**
     * @inheritDoc
     */
    public function resolve(string $key, string $label, ?string $attribute): ?string
    {
        $labels = $this->labels($attribute);

        return $labels === [] ? $key : $this->keys->resolve($key, $label, $labels);
    }

    /**
     * The admin labels of the attribute's options, by value; empty for an attribute without options
     *
     * @param string|null $attribute
     * @return array<string, string>
     */
    private function labels(?string $attribute): array
    {
        if ($attribute === null || $attribute === '') {
            return [];
        }
        $labels = [];
        foreach ($this->options->getOptions($attribute) as $option) {
            if ($option['value'] !== '') {
                $labels[$option['value']] = $option['label'];
            }
        }

        return $labels;
    }
}
