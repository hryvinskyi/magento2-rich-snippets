<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Test\Unit\Model\Listing;

use Hryvinskyi\RichSnippets\Model\Listing\PropertyCount;
use PHPUnit\Framework\TestCase;

/**
 * A template counts the properties of its root and of the objects directly inside it, never a key starting with
 * `@` and never the inside of a list, a value or an included snippet
 */
class PropertyCountTest extends TestCase
{
    public function testTheRootAndTheObjectsDirectlyInsideItAreCounted(): void
    {
        $template = (string) json_encode([
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => '{{product.name}}',
            'image' => ['@each' => 'product.gallery', '@value' => '{{image.url}}'],
            'brand' => ['@type' => 'Brand', 'name' => 'Example'],
            'offers' => [
                '@type' => 'Offer',
                'price' => '{{product.final_price}}',
                'priceSpecification' => ['@type' => 'UnitPriceSpecification', 'price' => '1', 'priceType' => 'x'],
                'hasMerchantReturnPolicy' => ['@include' => 'return-policy'],
            ],
            'review' => ['@each' => 'reviews.latest', '@type' => 'Review', 'author' => ['name' => 'x']],
            'policy' => ['@include' => 'return-policy'],
        ]);

        self::assertSame(10, (new PropertyCount())->of($template));
    }

    public function testATemplateWithoutAnObjectRootCountsNothing(): void
    {
        self::assertSame(0, (new PropertyCount())->of('[1, 2]'));
        self::assertSame(0, (new PropertyCount())->of('not json'));
    }
}
