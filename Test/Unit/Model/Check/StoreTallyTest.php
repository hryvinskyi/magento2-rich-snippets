<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Test\Unit\Model\Check;

use Hryvinskyi\RichSnippets\Api\Data\FeatureVerdict;
use Hryvinskyi\RichSnippets\Api\Data\OutputCheckInterface;
use Hryvinskyi\RichSnippets\Api\Data\PropertyTone;
use Hryvinskyi\RichSnippets\Api\Data\ResultTypeRuleInterface;
use Hryvinskyi\RichSnippets\Api\Data\SnippetInterface;
use Hryvinskyi\RichSnippets\Model\Check\AggregateMetric;
use Hryvinskyi\RichSnippets\Model\Check\FindingKeys;
use Hryvinskyi\RichSnippets\Model\Check\FindingMarks;
use Hryvinskyi\RichSnippets\Model\Check\MergedAggregates;
use Hryvinskyi\RichSnippets\Model\Check\StoreTally;
use Hryvinskyi\RichSnippets\Model\Page\PageEntity;
use Hryvinskyi\RichSnippets\Model\Requirement\Check\FeatureCheck;
use Hryvinskyi\RichSnippets\Model\Requirement\Check\MissingProperty;
use Hryvinskyi\RichSnippets\Model\Requirement\Check\OutputCheck;
use Hryvinskyi\RichSnippets\Test\Unit\Fake\ModuleRules;
use PHPUnit\Framework\TestCase;

/**
 * A store view's tally counts each page once per gap, whatever the number of its blocks showing it; a bad gap makes
 * the page invalid and a recommended one gives it a gap; a result left out is one note; a second snippet of a type
 * on a page is counted against it; the pages of each gap are handed over once
 */
class StoreTallyTest extends TestCase
{
    private const STORE = 1;

    public function testEachGapCountsAPageOnceAndKeepsTheSnippetsThatShowIt(): void
    {
        $tally = $this->tally();
        $product = $this->snippet('product', 'Product');
        $variants = $this->snippet('variants', 'Product');
        $ean = $this->missing('gtin13', PropertyTone::Warn, ['product.ean']);

        $tally->addPage(new PageEntity('product', 11, self::STORE), [$product, $variants], [
            [$product, $this->check(FeatureVerdict::Met, [], [$ean])],
            [$variants, $this->check(FeatureVerdict::Met, [], [$ean])],
        ]);
        $tally->addPage(new PageEntity('product', 12, self::STORE), [$product], [
            [$product, $this->check(FeatureVerdict::Met, [], [$ean])],
        ]);

        $finding = $tally->findings()['merchant-listing-gtin13-product-ean'] ?? null;
        self::assertNotNull($finding);
        self::assertSame(2, $finding['pages']);
        self::assertSame(['product' => 2], $finding['kinds']);
        self::assertSame(['product', 'variants'], array_keys($finding['snippets']));
        self::assertSame(PropertyTone::Warn, $finding['tone']);
        self::assertSame(['product.ean'], $finding['sources']);
    }

    public function testABadGapMakesThePageInvalidAndARecommendedOneGivesAValidPageAGap(): void
    {
        $tally = $this->tally();
        $product = $this->snippet('product', 'Product');
        $image = $this->missing('image', PropertyTone::Bad, ['product.image']);
        $description = $this->missing('description', PropertyTone::Warn, ['product.short_description']);

        $tally->addPage(new PageEntity('product', 1, self::STORE), [$product], [
            [$product, $this->check(FeatureVerdict::NotMet, [$image], [$description])],
        ]);
        $tally->addPage(new PageEntity('product', 2, self::STORE), [$product], [
            [$product, $this->check(FeatureVerdict::Met, [], [$description])],
        ]);
        $tally->addPage(new PageEntity('product', 3, self::STORE), [$product], [
            [$product, $this->check(FeatureVerdict::Met)],
        ]);
        $tally->addPage(new PageEntity('category', 4, self::STORE), [], []);

        $counts = $this->counts($tally);
        self::assertSame(['product' => 1], $counts['invalid_pages']);
        self::assertSame(['product' => 1], $counts['gap_pages']);
        self::assertSame(['product' => 3, 'category' => 1], $counts['pages']);
        self::assertSame(['' => 3], $counts['pages_with_snippets']);
        self::assertSame(['merchant_listing' => 2], $counts['feature_met']);
        self::assertSame(['merchant_listing' => 1], $counts['feature_not_met']);
    }

    public function testAResultLeftOutIsOneNoteWhateverItLacks(): void
    {
        $tally = $this->tally();
        $product = $this->snippet('product', 'Product');
        $check = new OutputCheck('Product', [
            new FeatureCheck($this->rule('review_stars'), FeatureVerdict::LeftOut, [
                new MissingProperty('review_stars', 'aggregateRating.ratingValue', PropertyTone::Note, [
                    'reviews.rating',
                ]),
                new MissingProperty('review_stars', 'aggregateRating.reviewCount', PropertyTone::Note, [
                    'reviews.count',
                ]),
            ]),
        ]);

        $tally->addPage(new PageEntity('product', 7, self::STORE), [$product], [[$product, $check]]);

        $findings = $tally->findings();
        self::assertSame(['review-stars-left-out-reviews-rating-reviews-count'], array_keys($findings));
        $finding = $findings['review-stars-left-out-reviews-rating-reviews-count'];
        self::assertSame(PropertyTone::Note, $finding['tone']);
        self::assertSame('', $finding['path']);
        self::assertArrayNotHasKey('invalid_pages', $this->counts($tally));
        self::assertArrayNotHasKey('gap_pages', $this->counts($tally));
        self::assertSame(['review_stars' => 1], $this->counts($tally)['feature_left_out']);
    }

    public function testASnippetSelectedAfterAnotherOfItsTypeIsCountedAgainstIt(): void
    {
        $tally = $this->tally();
        $first = $this->snippet('product', 'Product');
        $imported = $this->snippet('product-imported', 'Product');
        $crumbs = $this->snippet('breadcrumbs', 'BreadcrumbList');

        $tally->addPage(new PageEntity('product', 1, self::STORE), [$first, $crumbs, $imported], []);
        $tally->addPage(new PageEntity('product', 2, self::STORE), [$first, $imported], []);

        self::assertSame(['product-imported' => 2], $this->counts($tally)['second_of_type']);
    }

    public function testThePagesOfEachGapAreHandedOverOnce(): void
    {
        $tally = $this->tally();
        $product = $this->snippet('product', 'Product');
        $image = $this->missing('image', PropertyTone::Bad, []);

        $tally->addPage(new PageEntity('product', 5, self::STORE), [$product], [
            [$product, $this->check(FeatureVerdict::NotMet, [$image])],
        ]);
        $tally->addPage(new PageEntity('home', null, self::STORE), [$product], [
            [$product, $this->check(FeatureVerdict::NotMet, [$image])],
        ]);

        self::assertSame(
            ['merchant-listing-image' => [['kind' => 'product', 'entity' => 5], ['kind' => 'home', 'entity' => 0]]],
            $tally->takeItems()
        );
        self::assertSame([], $tally->takeItems());
        self::assertSame(2, $tally->findings()['merchant-listing-image']['pages']);
    }

    public function testTheCountsOverEveryStoreViewAddUpOnlyWhatIsNotCountedPerPage(): void
    {
        $merged = new MergedAggregates();
        $row = static fn (AggregateMetric $metric, string $subject, int $value): array => [
            'metric' => $metric,
            'subject' => $subject,
            'value' => $value,
        ];

        $merged->add([
            $row(AggregateMetric::Pages, 'product', 10),
            $row(AggregateMetric::FeatureMet, 'merchant_listing', 9),
            $row(AggregateMetric::SecondOfType, 'b', 1),
        ]);
        $merged->add([
            $row(AggregateMetric::InvalidPages, 'product', 2),
            $row(AggregateMetric::FeatureMet, 'merchant_listing', 8),
        ]);

        self::assertEquals(
            [
                $row(AggregateMetric::FeatureMet, 'merchant_listing', 17),
                $row(AggregateMetric::SecondOfType, 'b', 1),
            ],
            $merged->rows()
        );
    }

    /**
     * A tally of the store view
     *
     * @return StoreTally
     */
    private function tally(): StoreTally
    {
        return new StoreTally(new FindingMarks(new FindingKeys()), self::STORE);
    }

    /**
     * The counts of a tally by metric, then subject
     *
     * @param StoreTally $tally
     * @return array<string, array<string, int>>
     */
    private function counts(StoreTally $tally): array
    {
        $counts = [];
        foreach ($tally->aggregates() as $row) {
            $counts[$row['metric']->value][$row['subject']] = $row['value'];
        }

        return $counts;
    }

    /**
     * A merchant listing check
     *
     * @param FeatureVerdict $verdict
     * @param list<MissingProperty> $required
     * @param list<MissingProperty> $recommended
     * @return OutputCheckInterface
     */
    private function check(FeatureVerdict $verdict, array $required = [], array $recommended = []): OutputCheckInterface
    {
        return new OutputCheck('Product', [
            new FeatureCheck($this->rule('merchant_listing'), $verdict, $required, $recommended),
        ]);
    }

    /**
     * A missing property of a merchant listing
     *
     * @param string $path
     * @param PropertyTone $tone
     * @param list<string> $sources
     * @return MissingProperty
     */
    private function missing(string $path, PropertyTone $tone, array $sources): MissingProperty
    {
        return new MissingProperty('merchant_listing', $path, $tone, $sources);
    }

    /**
     * A rule of the module
     *
     * @param string $code
     * @return ResultTypeRuleInterface
     */
    private function rule(string $code): ResultTypeRuleInterface
    {
        $rule = ModuleRules::pool()->get($code);
        self::assertNotNull($rule);

        return $rule;
    }

    /**
     * A snippet with a code and a type
     *
     * @param string $code
     * @param string $type
     * @return SnippetInterface
     */
    private function snippet(string $code, string $type): SnippetInterface
    {
        $snippet = $this->createMock(SnippetInterface::class);
        $snippet->method('getCode')->willReturn($code);
        $snippet->method('getType')->willReturn($type);

        return $snippet;
    }
}
