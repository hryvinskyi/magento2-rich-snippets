<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Test\Unit\Model\Check;

use Hryvinskyi\RichSnippets\Api\Data\PropertyTone;
use Hryvinskyi\RichSnippets\Api\FieldCatalogInterface;
use Hryvinskyi\RichSnippets\Model\Check\Finding;
use Hryvinskyi\RichSnippets\Model\Check\FindingWording;
use Hryvinskyi\RichSnippets\Model\Wording\CountWords;
use Hryvinskyi\RichSnippets\Test\Unit\Fake\EnglishPlurals;
use Hryvinskyi\RichSnippets\Test\Unit\Fake\ModuleRules;
use Hryvinskyi\RichSnippets\Test\Unit\Fake\BuildsCheckWording;
use PHPUnit\Framework\TestCase;

/**
 * A finding is named by its rule's title for the requirement, else by the field the template read, else by its
 * path; it says why it matters and what to do in its tone, and counts products only when all its pages are products
 */
class FindingWordingTest extends TestCase
{
    use BuildsCheckWording;

    private const LABELS = ['product.ean' => 'EAN', 'product.short_description' => 'Short description'];

    public function testARequirementWithATitleIsNamedByIt(): void
    {
        $image = $this->finding('image', PropertyTone::Bad, ['product.image']);
        $wording = $this->findingWording(self::LABELS + ['product.image' => 'Main image']);

        self::assertSame('No image', $wording->title($image));
        self::assertSame(
            'Google needs image for a merchant listing. These pages are output without it and are reported as invalid.',
            $wording->description($image)
        );
        self::assertSame('Fill Main image for each of these pages.', $wording->fix($image));
    }

    public function testAGapInAFieldIsNamedByTheField(): void
    {
        $ean = $this->finding('gtin13', PropertyTone::Warn, ['product.ean']);
        $wording = $this->findingWording(self::LABELS);

        self::assertSame('EAN is empty', $wording->title($ean));
        self::assertSame(
            'gtin13 is left out. Google recommends it for a merchant listing.',
            $wording->description($ean)
        );
        self::assertSame(
            'Fill EAN where there is a value. Where there is none, the property is simply left out.',
            $wording->fix($ean)
        );
    }

    public function testAGapWithNoKnownFieldIsNamedByItsPath(): void
    {
        $brand = $this->finding('brand.name', PropertyTone::Warn, ['product.no_such_field']);

        self::assertSame('brand.name is missing', $this->findingWording([])->title($brand));
    }

    public function testAResultLeftOutSaysItsRulesTextAndNeedsNothing(): void
    {
        $stars = new Finding('rev', PropertyTone::Note, 'review_stars', '', [], ['product'], 71, ['product' => 71]);
        $wording = $this->findingWording([]);

        self::assertSame('Review stars left out', $wording->title($stars));
        self::assertSame(
            'The product has no reviews yet, so the rating is left out. Nothing to fix.',
            $wording->description($stars)
        );
        self::assertSame('Nothing to fix.', $wording->fix($stars));
    }

    public function testAGapThatNeedsNothingSaysWhyItIsShownAndHasNothingToFix(): void
    {
        $brand = $this->finding('brand', PropertyTone::Note, ['product.manufacturer']);
        $wording = $this->findingWording(self::LABELS);

        self::assertSame('brand is left out. Nothing to fix.', $wording->description($brand));
        self::assertSame('Nothing to fix.', $wording->fix($brand));
    }

    public function testAToneWithoutAWordingFailsLoudly(): void
    {
        $wording = new FindingWording(
            ModuleRules::pool(),
            $this->createMock(FieldCatalogInterface::class),
            new CountWords(new EnglishPlurals()),
            new EnglishPlurals()
        );

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('No wording is configured for the tone "warn".');

        $wording->fix($this->finding('gtin13', PropertyTone::Warn, []));
    }

    public function testOnlyPagesThatAreAllProductsAreCountedAsProducts(): void
    {
        $wording = $this->findingWording([]);
        $products = $this->finding('image', PropertyTone::Bad, []);
        $mixed = new Finding('x', PropertyTone::Bad, 'breadcrumb', 'itemListElement', [], ['crumbs'], 3, [
            'product' => 2,
            'category' => 1,
        ]);

        self::assertSame('23 products', $wording->count($products, 23));
        self::assertSame('1 product', $wording->count($products, 1));
        self::assertSame('3 pages', $wording->count($mixed, 3));
        self::assertSame('1 page', $wording->count($mixed, 1));
    }

    /**
     * A finding of a merchant listing on product pages
     *
     * @param string $path
     * @param PropertyTone $tone
     * @param list<string> $sources
     * @return Finding
     */
    private function finding(string $path, PropertyTone $tone, array $sources): Finding
    {
        return new Finding('key', $tone, 'merchant_listing', $path, $sources, ['product'], 23, ['product' => 23]);
    }
}
