<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Field\Attribute;

use Hryvinskyi\RichSnippets\Api\AttributeValueResolverInterface;

/**
 * The attribute value resolvers, by input type; an input type without one of its own is read as text
 */
class AttributeValueResolverPool
{
    /**
     * @param AttributeValueResolverInterface $fallback
     * @param array<string,AttributeValueResolverInterface> $resolvers by input type
     */
    public function __construct(
        private readonly AttributeValueResolverInterface $fallback,
        private readonly array $resolvers = []
    ) {
    }

    /**
     * The resolver of an input type
     *
     * @param string $input
     * @return AttributeValueResolverInterface
     */
    public function get(string $input): AttributeValueResolverInterface
    {
        return $this->resolvers[$input] ?? $this->fallback;
    }
}
