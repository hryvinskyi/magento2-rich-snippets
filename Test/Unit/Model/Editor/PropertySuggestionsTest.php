<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Test\Unit\Model\Editor;

use Hryvinskyi\RichSnippets\Model\Editor\PropertySuggestions;
use Hryvinskyi\RichSnippets\Test\Unit\Fake\ModuleRules;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The properties offered when a property is added: a configured list for a type, else what the rules ask for; and
 * the field a property named by hand is taken from, whether its name is offered or not
 */
class PropertySuggestionsTest extends TestCase
{
    private const DESIGN = [
        'Product' => [
            'mpn' => ['name' => 'mpn'],
            'material' => ['name' => 'material', 'field' => 'product.material'],
            'color' => ['name' => 'color', 'field' => 'product.color'],
        ],
        'Offer' => [
            'validFrom' => ['name' => 'validFrom'],
        ],
    ];

    public function testAConfiguredTypeIsOfferedItsOwnListInOrderWithTheFieldsItNames(): void
    {
        $suggestions = (new PropertySuggestions(ModuleRules::pool(), self::DESIGN))->byType();

        self::assertSame(
            [
                '' => [
                    ['name' => 'mpn', 'required' => false, 'field' => null],
                    ['name' => 'material', 'required' => false, 'field' => 'product.material'],
                    ['name' => 'color', 'required' => false, 'field' => 'product.color'],
                ],
            ],
            $suggestions['Product'] ?? null
        );
        self::assertSame(
            ['' => [['name' => 'validFrom', 'required' => false, 'field' => null]]],
            $suggestions['Offer'] ?? null
        );
    }

    public function testANameTypedInTakesTheFieldOfItsSuggestionOrTheFieldConfiguredForIt(): void
    {
        $suggestions = new PropertySuggestions(
            ModuleRules::pool(),
            ['Product' => [['name' => 'mpn'], ['name' => 'material', 'field' => 'product.material']]],
            ['Product' => ['color' => 'product.color'], 'Offer' => ['seller' => 'store.name']]
        );

        self::assertSame(
            [
                'Product' => ['material' => 'product.material', 'color' => 'product.color'],
                'Offer' => ['seller' => 'store.name'],
            ],
            $suggestions->fieldsByName()
        );
        self::assertSame(['mpn', 'material'], array_column($suggestions->byType()['Product'][''] ?? [], 'name'));
    }

    /**
     * @param array<array-key, mixed> $fields
     */
    #[DataProvider('malformedFields')]
    public function testAMalformedFieldByNameIsRefusedWhenTheSuggestionsAreBuilt(array $fields): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new PropertySuggestions(ModuleRules::pool(), [], $fields);
    }

    /**
     * @return array<string, array{array<array-key, mixed>}>
     */
    public static function malformedFields(): array
    {
        return [
            'a type that is not a schema.org name' => [['product' => ['color' => 'product.color']]],
            'names that are not a map' => [['Product' => 'color']],
            'a name with a space' => [['Product' => ['main colour' => 'product.color']]],
            'a field that is not a path' => [['Product' => ['color' => 'color']]],
            'a field that is not text' => [['Product' => ['color' => 7]]],
        ];
    }

    public function testATypeWithoutAListFallsBackToWhatTheRulesAskForAtEachLevel(): void
    {
        $product = (new PropertySuggestions(ModuleRules::pool()))->byType()['Product'] ?? [];

        self::assertSame(
            ['name', 'image', 'offers', 'aggregateRating', 'description', 'sku', 'gtin', 'brand', 'review'],
            array_column($product[''] ?? [], 'name')
        );
        self::assertSame(
            ['name' => true, 'image' => true, 'offers' => true, 'aggregateRating' => true],
            array_filter(array_column($product[''] ?? [], 'required', 'name'))
        );
        self::assertSame(
            [
                ['name' => 'price', 'required' => true, 'field' => null],
                ['name' => 'priceCurrency', 'required' => true, 'field' => null],
                ['name' => 'availability', 'required' => false, 'field' => null],
                ['name' => 'itemCondition', 'required' => false, 'field' => null],
                ['name' => 'hasMerchantReturnPolicy', 'required' => false, 'field' => null],
                ['name' => 'shippingDetails', 'required' => false, 'field' => null],
            ],
            $product['offers'] ?? []
        );
        self::assertSame([['name' => 'name', 'required' => false, 'field' => null]], $product['brand'] ?? []);
    }

    public function testATypeWithoutAListKeepsItsRulesWhileOtherTypesAreConfigured(): void
    {
        $suggestions = (new PropertySuggestions(ModuleRules::pool(), self::DESIGN))->byType();

        self::assertSame(
            ['applicableCountry', 'returnPolicyCategory', 'merchantReturnDays', 'returnMethod', 'returnFees'],
            array_column($suggestions['OnlineStore']['hasMerchantReturnPolicy'] ?? [], 'name')
        );
        self::assertSame(
            array_column($suggestions['OnlineStore']['hasMerchantReturnPolicy'] ?? [], 'name'),
            array_column($suggestions['MerchantReturnPolicy'][''] ?? [], 'name')
        );
    }

    /**
     * @param array<array-key, mixed> $suggestions
     */
    #[DataProvider('malformedLists')]
    public function testAMalformedListIsRefusedWhenTheSuggestionsAreBuilt(array $suggestions): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new PropertySuggestions(ModuleRules::pool(), $suggestions);
    }

    /**
     * @return array<string, array{array<array-key, mixed>}>
     */
    public static function malformedLists(): array
    {
        return [
            'a type that is not a schema.org name' => [['product' => [['name' => 'mpn']]]],
            'a list that is not a list' => [['Product' => 'mpn']],
            'an entry without a name' => [['Product' => [['field' => 'product.material']]]],
            'a name with a space' => [['Product' => [['name' => 'part number']]]],
            'a field that is not a path' => [['Product' => [['name' => 'material', 'field' => 'material']]]],
            'a field that is not text' => [['Product' => [['name' => 'material', 'field' => 7]]]],
        ];
    }
}
