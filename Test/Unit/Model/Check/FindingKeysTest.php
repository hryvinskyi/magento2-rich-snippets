<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Test\Unit\Model\Check;

use Hryvinskyi\RichSnippets\Model\Check\FindingKeys;
use PHPUnit\Framework\TestCase;

/**
 * A finding's key names the result type, the property and the variables read, in letters, digits and dashes, and
 * stays within the stored length without two gaps ever sharing one
 */
class FindingKeysTest extends TestCase
{
    public function testTheKeyReadsAsTheResultThePropertyAndTheVariables(): void
    {
        $keys = new FindingKeys();

        self::assertSame(
            'merchant-listing-gtin13-product-ean',
            $keys->key('merchant_listing', 'gtin13', ['product.ean'])
        );
        self::assertSame('merchant-listing-image', $keys->key('merchant_listing', 'image', []));
        self::assertSame(
            'review-stars-left-out-reviews-rating',
            $keys->key('review_stars', '', ['reviews.rating'])
        );
    }

    public function testTheSameGapAlwaysHasTheSameKey(): void
    {
        $keys = new FindingKeys();

        self::assertSame(
            $keys->key('merchant_listing', 'offers.price', ['product.final_price']),
            $keys->key('merchant_listing', 'offers.price', ['product.final_price'])
        );
    }

    public function testALongKeyKeepsItsStartAndEndsInADigestOfTheWhole(): void
    {
        $keys = new FindingKeys();
        $long = str_repeat('attribute_', 20);

        $first = $keys->key('merchant_listing', 'description', ['product.' . $long . 'a']);
        $second = $keys->key('merchant_listing', 'description', ['product.' . $long . 'b']);

        self::assertLessThanOrEqual(191, strlen($first));
        self::assertStringStartsWith('merchant-listing-description-product-attribute-', $first);
        self::assertNotSame($first, $second);
        self::assertMatchesRegularExpression('/\A[a-z0-9-]+\z/', $first);
    }
}
