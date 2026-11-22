<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Test\Unit\Model\Library;

use Hryvinskyi\JsonTemplate\Engine\Template\TemplateLiterals;
use Hryvinskyi\RichSnippets\Api\Data\HintTone;
use Hryvinskyi\RichSnippets\Model\Library\AnswerSubstitution;
use Hryvinskyi\RichSnippets\Model\Library\Question\AnswerOption;
use PHPUnit\Framework\TestCase;

/**
 * Answers go into a library template node by node; text from the shop, written in the engine's literal escapes, stays
 * valid JSON and keeps its escapes through the substitution
 */
class AnswerSubstitutionTest extends TestCase
{
    private const SHOP = 'O\'Hara "&" }} | Co {{x}}';

    private const TEMPLATE = [
        '@context' => 'https://schema.org',
        '@type' => 'Product',
        'name' => '{{product.name}}',
        'description' => '{{? description }}',
        'gtin13' => '{{?gtin}}',
        'brand' => ['@type' => 'Brand', 'name' => '{{? brand }}'],
        'offers' => [
            '@type' => 'Offer',
            'hasMerchantReturnPolicy' => ['@include' => 'return-policy'],
        ],
        'sameAs' => ['{{? profile }}'],
    ];

    public function testTheBrandAnswerCarriesTheShopNameLiterallyAndTheTemplateStaysValidJson(): void
    {
        $literals = new TemplateLiterals();
        $fallback = '{{product.manufacturer | default:' . $literals->expressionString(self::SHOP) . '}}';

        $result = (new AnswerSubstitution())->substitute(
            self::TEMPLATE,
            [
                'description' => $this->answer('{{product.short_description | plain}}'),
                'gtin' => $this->answer('{{product.ean}}'),
                'brand' => $this->answer($fallback),
                'profile' => $this->answer($literals->text('https://example.com/{{shop}}')),
            ],
            []
        );
        $json = json_encode($result, JSON_THROW_ON_ERROR);

        self::assertIsArray($result['brand']);
        self::assertSame(
            '{{product.manufacturer | default:\'O\\\'Hara "&" }} | Co {{x}}\'}}',
            $result['brand']['name']
        );
        self::assertSame($result, json_decode($json, true, 512, JSON_THROW_ON_ERROR));
        self::assertSame(['https://example.com/{{{{shop}}'], $result['sameAs']);
        self::assertSame('{{product.name}}', $result['name'], 'Nodes without a placeholder stay as they are.');
    }

    public function testAnAnswerThatLeavesThePropertyOutRemovesItsKey(): void
    {
        $result = (new AnswerSubstitution())->substitute(
            self::TEMPLATE,
            [
                'description' => $this->leaveOut(),
                'gtin' => $this->leaveOut(),
                'brand' => $this->answer('{{product.manufacturer}}'),
                'profile' => $this->answer('https://example.com'),
            ],
            []
        );

        self::assertArrayNotHasKey('description', $result);
        self::assertArrayNotHasKey('gtin13', $result);
        self::assertSame(['@type' => 'Brand', 'name' => '{{product.manufacturer}}'], $result['brand']);
    }

    public function testAnObjectOrAListLeftWithNothingOfItsOwnGoesWithTheRemovedProperty(): void
    {
        $result = (new AnswerSubstitution())->substitute(
            self::TEMPLATE,
            [
                'description' => $this->answer('{{product.description}}'),
                'gtin' => $this->answer('{{product.ean}}'),
                'brand' => $this->leaveOut(),
                'profile' => $this->leaveOut(),
            ],
            []
        );

        self::assertArrayNotHasKey('brand', $result, 'A Brand with only its type is left out.');
        self::assertArrayNotHasKey('sameAs', $result, 'A list emptied by the removal is left out.');
        self::assertSame(
            ['@type' => 'Offer', 'hasMerchantReturnPolicy' => ['@include' => 'return-policy']],
            $result['offers'],
            'An object that had only its type to begin with is not touched.'
        );
    }

    public function testAnIncludeOfABuildingBlockPointsAtTheShopsSnippet(): void
    {
        $result = (new AnswerSubstitution())->substitute(
            ['@type' => 'Product', 'a' => ['@include' => 'return-policy'], 'b' => ['@include' => 'other']],
            [],
            ['return-policy' => 'return-policy-2']
        );

        self::assertSame(['@include' => 'return-policy-2'], $result['a']);
        self::assertSame(['@include' => 'other'], $result['b'], 'An include of no listed block is kept.');
    }

    public function testTheRootStaysWhenEveryPropertyIsLeftOut(): void
    {
        $result = (new AnswerSubstitution())->withoutAnswers(
            ['@context' => 'https://schema.org', '@type' => 'WebSite', 'name' => '{{? name }}']
        );

        self::assertSame(['@context' => 'https://schema.org', '@type' => 'WebSite'], $result);
    }

    public function testThePlaceholdersAreListedOnceEachInTheOrderMet(): void
    {
        $substitution = new AnswerSubstitution();

        self::assertSame(
            ['description', 'gtin', 'brand', 'profile'],
            $substitution->placeholders(self::TEMPLATE + ['again' => '{{? brand }}'])
        );
        self::assertTrue($substitution->looksLikeBrokenPlaceholder('text {{? brand }}'));
        self::assertTrue($substitution->looksLikeBrokenPlaceholder('{{? Brand }}'));
        self::assertFalse($substitution->looksLikeBrokenPlaceholder('{{ product.name }}'));
        self::assertFalse($substitution->looksLikeBrokenPlaceholder('{{?  brand_name  }}'));
    }

    public function testAPlaceholderWithoutAnAnswerIsRefused(): void
    {
        $this->expectException(\OutOfBoundsException::class);
        $this->expectExceptionMessage('"brand"');

        (new AnswerSubstitution())->substitute(['@type' => 'Product', 'b' => '{{? brand }}'], [], []);
    }

    /**
     * An answer with a fragment
     *
     * @param mixed $fragment
     * @return AnswerOption
     */
    private function answer(mixed $fragment): AnswerOption
    {
        return new AnswerOption('key', 'Label', false, $fragment, 'Hint', HintTone::Ok);
    }

    /**
     * An answer that leaves the property out
     *
     * @return AnswerOption
     */
    private function leaveOut(): AnswerOption
    {
        return new AnswerOption('leave_out', 'Leave it out', true, null, 'Hint', HintTone::Warn);
    }
}
