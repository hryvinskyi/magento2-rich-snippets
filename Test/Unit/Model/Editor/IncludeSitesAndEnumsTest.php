<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Test\Unit\Model\Editor;

use Hryvinskyi\RichSnippets\Model\Editor\IncludeSites;
use Hryvinskyi\RichSnippets\Model\Editor\PropertyEnums;
use PHPUnit\Framework\TestCase;

/**
 * Where a template includes a snippet is named by the properties on the way; enumerated values are configuration
 * that is checked when it is read
 */
class IncludeSitesAndEnumsTest extends TestCase
{
    public function testAnIncludeIsNamedByThePropertiesOnTheWayToIt(): void
    {
        $template = (string) json_encode([
            '@type' => 'Product',
            'offers' => ['@type' => 'Offer', 'hasMerchantReturnPolicy' => ['@include' => 'return-policy']],
            'hasVariant' => [
                '@each' => 'product.variants',
                'offers' => [
                    'hasMerchantReturnPolicy' => ['@if' => 'variant.sku', '@include' => 'return-policy'],
                ],
            ],
            'subjectOf' => [['@value' => ['@include' => 'return-policy']], ['@include' => 'other']],
            'brand' => ['@include' => 'brand'],
        ]);

        self::assertSame(
            ['offers.hasMerchantReturnPolicy', 'hasVariant.offers.hasMerchantReturnPolicy', 'subjectOf'],
            (new IncludeSites())->of($template, 'return-policy')
        );
    }

    public function testAnIncludeAtTheRootHasTheEmptyPathAndATemplateThatIsNotJsonHasNone(): void
    {
        self::assertSame([''], (new IncludeSites())->of('{"@include": "policy"}', 'policy'));
        self::assertSame([], (new IncludeSites())->of('{"@include": ', 'policy'));
    }

    public function testEnumeratedValuesAreKeptByPropertyWithoutRepeats(): void
    {
        $enums = new PropertyEnums([
            'itemCondition' => ['new' => 'NewCondition', 'used' => 'UsedCondition', 'again' => 'NewCondition'],
        ]);

        self::assertSame(['itemCondition' => ['NewCondition', 'UsedCondition']], $enums->all());
    }

    public function testAValueThatIsNoEnumerationMemberIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('itemCondition');

        new PropertyEnums(['itemCondition' => ['https://schema.org/NewCondition']]);
    }
}
