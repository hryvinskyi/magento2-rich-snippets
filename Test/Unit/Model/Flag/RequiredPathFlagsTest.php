<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Test\Unit\Model\Flag;

use Hryvinskyi\JsonTemplate\Api\Data\CompiledTemplateInterface;
use Hryvinskyi\JsonTemplate\Api\TemplateEngineInterface;
use Hryvinskyi\RichSnippets\Api\Data\FlagTone;
use Hryvinskyi\RichSnippets\Api\Data\PropertyTone;
use Hryvinskyi\RichSnippets\Api\Data\SnippetFlagInterface;
use Hryvinskyi\RichSnippets\Api\RequirementCheckerInterface;
use Hryvinskyi\RichSnippets\Api\SnippetFlagFactoryInterface;
use Hryvinskyi\RichSnippets\Model\Flag\Provider\RequiredPathFlags;
use Hryvinskyi\RichSnippets\Model\Flag\SnippetFlag;
use Hryvinskyi\RichSnippets\Model\Requirement\Check\MissingProperty;
use Hryvinskyi\RichSnippets\Model\Requirement\ResultTypeRulePool;
use Hryvinskyi\RichSnippets\Model\Snippet;
use Hryvinskyi\RichSnippets\Test\Unit\Fake\ModuleRules;
use Hryvinskyi\RichSnippets\Test\Unit\Fake\PlacementFixture;
use PHPUnit\Framework\TestCase;

/**
 * A snippet whose template can never write a property Google needs is flagged with the title the rule gives the
 * property, in the design's words
 */
class RequiredPathFlagsTest extends TestCase
{
    public function testAMissingRequiredPropertyReadsAsItsRuleTitlesIt(): void
    {
        $flags = $this->flags([
            new MissingProperty('merchant_listing', 'image', PropertyTone::Bad),
            new MissingProperty('merchant_listing', 'offers.price', PropertyTone::Bad),
            new MissingProperty('merchant_listing', 'offers.priceCurrency', PropertyTone::Bad),
        ]);

        self::assertSame(
            [
                ['bad', 'No image'],
                ['bad', 'No offer: Google needs one'],
                ['bad', 'No currency for the price'],
            ],
            $flags
        );
    }

    public function testAMemberOfATitledGroupTakesTheGroupsTitle(): void
    {
        $flags = $this->flags([new MissingProperty('product_variants', 'hasVariant.gtin13', PropertyTone::Bad)]);

        self::assertSame([['bad', 'A variant has no SKU or GTIN']], $flags);
    }

    public function testAPropertyWithoutATitleIsNamedByItsPathAndResult(): void
    {
        $flags = $this->flags([new MissingProperty('merchant_listing', 'offers.shippingDetails', PropertyTone::Bad)]);

        self::assertSame(
            [['bad', 'offers.shippingDetails is missing: Google needs it for a merchant listing']],
            $flags
        );
    }

    public function testOnlyAPropertyGoogleNeedsForAResultItShowsIsFlaggedAndEachPathOnce(): void
    {
        $flags = $this->flags([
            new MissingProperty('review_stars', 'aggregateRating.ratingValue', PropertyTone::Note),
            new MissingProperty('merchant_listing', 'image', PropertyTone::Bad),
            new MissingProperty('merchant_listing', 'image', PropertyTone::Bad),
        ]);

        self::assertSame([['bad', 'No image']], $flags);
    }

    /**
     * The flags of a product snippet whose template misses the properties given, as tone and text
     *
     * @param list<MissingProperty> $missing
     * @return list<array{0: string, 1: string}>
     */
    private function flags(array $missing): array
    {
        $engine = $this->createStub(TemplateEngineInterface::class);
        $engine->method('compile')->willReturn($this->createStub(CompiledTemplateInterface::class));
        $checker = $this->createStub(RequirementCheckerInterface::class);
        $checker->method('checkTemplate')->willReturn($missing);
        $factory = new class implements SnippetFlagFactoryInterface {
            /**
             * @inheritDoc
             */
            public function create(FlagTone $tone, string $text): SnippetFlagInterface
            {
                return new SnippetFlag($tone, $text);
            }
        };
        $snippet = new Snippet(
            1,
            'product',
            'Product',
            '{"@type": "Product"}',
            'Product',
            false,
            true,
            ['product'],
            PlacementFixture::none(),
            [0],
            null,
            0,
            null,
            ''
        );
        $pool = ModuleRules::pool();
        self::assertInstanceOf(ResultTypeRulePool::class, $pool);

        $flags = (new RequiredPathFlags($engine, $checker, $pool, $factory))->flags([$snippet], null);

        return array_map(
            static fn (SnippetFlagInterface $flag): array => [$flag->getTone()->value, $flag->getText()],
            $flags['product'] ?? []
        );
    }
}
