<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Test\Unit\Model\Requirement;

use Hryvinskyi\JsonTemplate\Api\Data\CompiledTemplateInterface;
use Hryvinskyi\JsonTemplate\Api\Data\TraceOutcome;
use Hryvinskyi\RichSnippets\Api\Data\FeatureCheckInterface;
use Hryvinskyi\RichSnippets\Api\Data\FeatureVerdict;
use Hryvinskyi\RichSnippets\Api\Data\MissingPropertyInterface;
use Hryvinskyi\RichSnippets\Api\Data\OutputCheckInterface;
use Hryvinskyi\RichSnippets\Api\Data\PropertyTone;
use Hryvinskyi\RichSnippets\Api\Data\TallyStatus;
use Hryvinskyi\RichSnippets\Model\Requirement\RequirementChecker;
use Hryvinskyi\RichSnippets\Test\Unit\Fake\ModuleChecker;
use PHPUnit\Framework\TestCase;

/**
 * The module's rules applied to the admin's preview pages give the verdicts the page test shows for them; any-of
 * groups, optional results, nested results, lists and unknown types behave as the rules say; a template is read for
 * the required properties it can never write; tallies count verdicts per kind of result
 */
class RequirementCheckerTest extends TestCase
{
    public function testTheSatchelMeetsEverythingButDeliveryDetails(): void
    {
        $check = $this->checker()->checkOutput('Product', DesignPreviews::satchel());

        $listing = $this->feature($check, 'merchant_listing');
        self::assertSame(FeatureVerdict::Met, $listing->getVerdict());
        self::assertSame([], $listing->getMissingRequired());
        self::assertSame(['offers.shippingDetails'], $this->paths($listing->getMissingRecommended()));
        self::assertSame(FeatureVerdict::Met, $this->feature($check, 'review_stars')->getVerdict());
        self::assertSame(['merchant_listing', 'review_stars'], $this->codes($check));
    }

    public function testTheSleeveIsAValidListingWithGapsAndLeavesTheStarsOut(): void
    {
        $check = $this->checker()->checkOutput('Product', DesignPreviews::sleeve(), DesignPreviews::sleeveTrace());

        $listing = $this->feature($check, 'merchant_listing');
        self::assertSame(FeatureVerdict::Met, $listing->getVerdict());
        self::assertSame(
            ['gtin13', 'offers.shippingDetails', 'aggregateRating', 'review'],
            $this->paths($listing->getMissingRecommended())
        );
        self::assertSame(
            [PropertyTone::Warn, PropertyTone::Warn, PropertyTone::Warn, PropertyTone::Warn],
            array_map(
                static fn (MissingPropertyInterface $m): PropertyTone => $m->getTone(),
                $listing->getMissingRecommended()
            )
        );
        self::assertSame(
            [['product.ean'], [], ['reviews.rating', 'reviews.count'], ['reviews.latest']],
            array_map(
                static fn (MissingPropertyInterface $m): array => $m->getSources(),
                $listing->getMissingRecommended()
            )
        );

        $stars = $this->feature($check, 'review_stars');
        self::assertSame(FeatureVerdict::LeftOut, $stars->getVerdict());
        self::assertSame(
            ['aggregateRating.ratingValue', 'aggregateRating.reviewCount'],
            $this->paths($stars->getMissingRequired())
        );
        self::assertSame(PropertyTone::Note, $stars->getMissingRequired()[0]->getTone());
        self::assertSame(['reviews.rating'], $stars->getMissingRequired()[0]->getSources());
    }

    public function testTheSleevesLeftOutPropertiesAreTheOutermostOnesTheTemplateWrote(): void
    {
        $check = $this->checker()->checkOutput('Product', DesignPreviews::sleeve(), DesignPreviews::sleeveTrace());

        $leftOut = [];
        foreach ($check->getLeftOut() as $property) {
            $leftOut[$property->getPath()] = [$property->getOutcome(), $property->getSources()];
        }
        self::assertSame(
            [
                'gtin13' => [TraceOutcome::Empty, ['product.ean']],
                'offers.priceValidUntil' => [TraceOutcome::Skipped, ['product.special_to_date']],
                'aggregateRating' => [TraceOutcome::Empty, []],
                'review' => [TraceOutcome::Empty, ['reviews.latest']],
            ],
            $leftOut
        );
    }

    public function testTheKitWithoutAnImageIsNotAValidListing(): void
    {
        $check = $this->checker()->checkOutput('Product', DesignPreviews::kit());

        $listing = $this->feature($check, 'merchant_listing');
        self::assertSame(FeatureVerdict::NotMet, $listing->getVerdict());
        self::assertSame(['image'], $this->paths($listing->getMissingRequired()));
        self::assertSame(PropertyTone::Bad, $listing->getMissingRequired()[0]->getTone());
        self::assertSame(FeatureVerdict::LeftOut, $this->feature($check, 'review_stars')->getVerdict());
    }

    public function testTheHomePageMeetsOrganizationReturnPolicyAndSiteName(): void
    {
        $organization = $this->checker()->checkOutput('OnlineStore', DesignPreviews::organization());
        $website = $this->checker()->checkOutput('WebSite', DesignPreviews::website());

        self::assertSame(['organization', 'return_policy'], $this->codes($organization));
        $details = $this->feature($organization, 'organization');
        self::assertSame(FeatureVerdict::Met, $details->getVerdict());
        self::assertSame(['hasShippingService'], $this->paths($details->getMissingRecommended()));
        $policy = $this->feature($organization, 'return_policy');
        self::assertSame(FeatureVerdict::Met, $policy->getVerdict());
        self::assertSame([], $policy->getMissingRecommended());
        $siteName = $this->feature($website, 'site_name');
        self::assertSame(FeatureVerdict::Met, $siteName->getVerdict());
        self::assertSame([], $siteName->getMissingRecommended());
    }

    public function testBreadcrumbsWithAPositionNameAndAddressOnEveryLevelMeetTheBreadcrumb(): void
    {
        $check = $this->checker()->checkOutput('BreadcrumbList', DesignPreviews::breadcrumbs());

        self::assertSame(FeatureVerdict::Met, $this->feature($check, 'breadcrumb')->getVerdict());
    }

    public function testAPathThroughAListNeedsEveryItemToHoldIt(): void
    {
        $crumbs = DesignPreviews::breadcrumbs();
        $items = $crumbs['itemListElement'];
        self::assertIsArray($items);
        self::assertIsArray($items[1]);
        unset($items[1]['item']);
        $crumbs['itemListElement'] = $items;

        $breadcrumb = $this->feature($this->checker()->checkOutput('BreadcrumbList', $crumbs), 'breadcrumb');

        self::assertSame(FeatureVerdict::NotMet, $breadcrumb->getVerdict());
        self::assertSame(['itemListElement.item'], $this->paths($breadcrumb->getMissingRequired()));
    }

    public function testAnyGtinSatisfiesTheGtinRecommendation(): void
    {
        $product = DesignPreviews::satchel();
        unset($product['gtin13']);
        $product['gtin'] = '04006381333931';

        $listing = $this->feature($this->checker()->checkOutput('Product', $product), 'merchant_listing');

        self::assertSame(['offers.shippingDetails'], $this->paths($listing->getMissingRecommended()));
    }

    public function testAMissingGtinWithoutATraceIsNamedByTheGroupsFirstMember(): void
    {
        $check = $this->checker()->checkOutput('Product', DesignPreviews::sleeve());
        $listing = $this->feature($check, 'merchant_listing');

        self::assertSame('gtin', $listing->getMissingRecommended()[0]->getPath());
        self::assertSame([], $listing->getMissingRecommended()[0]->getSources());
    }

    public function testATypeGoogleHasNoResultForHasNoRule(): void
    {
        $check = $this->checker()->checkOutput('Thing', ['@type' => 'Thing', 'name' => 'x']);

        self::assertFalse($check->hasRule());
        self::assertSame([], $check->getFeatures());
        self::assertSame('Thing', $check->getType());
    }

    public function testEveryTypeOfARootTypeListBringsItsRules(): void
    {
        $store = DesignPreviews::organization();
        $store['@type'] = ['Store', 'OnlineStore'];

        $check = $this->checker()->checkOutput('Store', $store);

        self::assertSame(['organization', 'return_policy'], $this->codes($check));
    }

    public function testANestedResultWhoseObjectIsNotThereIsLeftOutWithNothingMissing(): void
    {
        $organization = DesignPreviews::organization();
        unset($organization['hasMerchantReturnPolicy']);

        $policy = $this->feature($this->checker()->checkOutput('OnlineStore', $organization), 'return_policy');

        self::assertSame(FeatureVerdict::LeftOut, $policy->getVerdict());
        self::assertSame([], $policy->getMissingRequired());
        self::assertSame([], $policy->getMissingRecommended());
    }

    public function testANestedResultReportsItsPathsFromTheRoot(): void
    {
        $organization = DesignPreviews::organization();
        $policy = DesignPreviews::returnPolicy();
        unset($policy['merchantReturnDays'], $policy['returnFees']);
        $organization['hasMerchantReturnPolicy'] = $policy;

        $feature = $this->feature($this->checker()->checkOutput('OnlineStore', $organization), 'return_policy');

        self::assertSame(FeatureVerdict::NotMet, $feature->getVerdict());
        self::assertSame(['hasMerchantReturnPolicy.merchantReturnDays'], $this->paths($feature->getMissingRequired()));
        self::assertSame(['hasMerchantReturnPolicy.returnFees'], $this->paths($feature->getMissingRecommended()));
    }

    public function testAResultGoogleNoLongerShowsReportsItsGapsAsNotes(): void
    {
        $faq = $this->feature($this->checker()->checkOutput('FAQPage', ['@type' => 'FAQPage']), 'faq');

        self::assertSame(FeatureVerdict::NotMet, $faq->getVerdict());
        self::assertSame(PropertyTone::Note, $faq->getMissingRequired()[0]->getTone());
    }

    public function testTalliesCountVerdictsPerKindOfResult(): void
    {
        $checker = $this->checker();
        $checks = [
            $checker->checkOutput('Product', DesignPreviews::satchel()),
            $checker->checkOutput('Product', DesignPreviews::sleeve()),
            $checker->checkOutput('Product', DesignPreviews::kit()),
            $checker->checkOutput('FAQPage', ['@type' => 'FAQPage', 'mainEntity' => [['@type' => 'Question']]]),
        ];

        $tallies = [];
        foreach ($checker->tally($checks) as $tally) {
            $tallies[$tally->getRule()->getCode()] = [
                $tally->getMet(), $tally->getNotMet(), $tally->getLeftOut(), $tally->getTotal(), $tally->getStatus(),
            ];
        }

        self::assertSame(
            [
                'merchant_listing' => [2, 1, 0, 3, TallyStatus::Partly],
                'review_stars' => [1, 0, 2, 3, TallyStatus::Met],
                'faq' => [1, 0, 0, 1, TallyStatus::NoResult],
            ],
            $tallies
        );
    }

    public function testATemplateWithoutAnOfferIsFoundMissingItsPriceAndCurrency(): void
    {
        $template = $this->template(['kind' => 'object', 'entries' => [
            ['@context', ['kind' => 'literal', 'value' => 'https://schema.org']],
            ['@type', ['kind' => 'literal', 'value' => 'Product']],
            ['name', ['kind' => 'interp', 'expression' => ['kind' => 'path', 'segments' => ['product', 'name']]]],
            ['image', ['kind' => 'list', 'items' => [
                ['kind' => 'directive', 'arguments' => [], 'body' => ['kind' => 'interp', 'expression' => []]],
            ]]],
            ['aggregateRating', ['kind' => 'directive', 'arguments' => [], 'body' => [
                'kind' => 'object',
                'entries' => [
                    ['ratingValue', ['kind' => 'interp', 'expression' => []]],
                    ['reviewCount', ['kind' => 'interp', 'expression' => []]],
                ],
            ]]],
        ]]);

        $missing = $this->checker()->checkTemplate($template);

        self::assertSame(
            [
                ['merchant_listing', 'offers.price', PropertyTone::Bad],
                ['merchant_listing', 'offers.priceCurrency', PropertyTone::Bad],
            ],
            array_map(
                static fn (MissingPropertyInterface $m): array => [$m->getRuleCode(), $m->getPath(), $m->getTone()],
                $missing
            )
        );
    }

    public function testATemplateMissesNothingBelowAWholeExpressionOrAnInclude(): void
    {
        $template = $this->template(['kind' => 'object', 'entries' => [
            ['@type', ['kind' => 'literal', 'value' => ['Product']]],
            ['name', ['kind' => 'text', 'parts' => ['A ', ['kind' => 'path', 'segments' => ['x']]]]],
            ['image', ['kind' => 'literal', 'value' => 'https://shop.example/a.jpg']],
            ['offers', ['kind' => 'include', 'code' => 'offer']],
            ['aggregateRating', ['kind' => 'interp', 'expression' => []]],
        ]]);

        self::assertSame([], $this->checker()->checkTemplate($template));
    }

    public function testATemplateWhoseTypeIsAnExpressionHasNoRuleToMiss(): void
    {
        $template = $this->template(['kind' => 'object', 'entries' => [
            ['@type', ['kind' => 'interp', 'expression' => []]],
        ]]);

        self::assertSame([], $this->checker()->checkTemplate($template));
    }

    public function testATemplateChecksANestedResultOnlyWhenItWritesItsObject(): void
    {
        $withoutPolicy = $this->template(['kind' => 'literal', 'value' => ['@type' => 'OnlineStore', 'name' => 'x']]);
        $withPolicy = $this->template(['kind' => 'directive', 'arguments' => [], 'body' => [
            'kind' => 'object',
            'entries' => [
                ['@type', ['kind' => 'literal', 'value' => 'OnlineStore']],
                ['hasMerchantReturnPolicy', ['kind' => 'literal', 'value' => [
                    'applicableCountry' => 'DE',
                    'returnPolicyCategory' => 'https://schema.org/MerchantReturnFiniteReturnWindow',
                ]]],
            ],
        ]]);

        self::assertSame([], $this->checker()->checkTemplate($withoutPolicy));
        self::assertSame(
            ['hasMerchantReturnPolicy.merchantReturnDays'],
            $this->paths($this->checker()->checkTemplate($withPolicy))
        );
    }

    /**
     * The checker over the module's rules and the template readers di.xml configures
     *
     * @return RequirementChecker
     */
    private function checker(): RequirementChecker
    {
        return ModuleChecker::create();
    }

    /**
     * A compiled template with a syntax tree
     *
     * @param array<string, mixed> $ast
     * @return CompiledTemplateInterface
     */
    private function template(array $ast): CompiledTemplateInterface
    {
        $template = $this->createStub(CompiledTemplateInterface::class);
        $template->method('getAst')->willReturn($ast);

        return $template;
    }

    /**
     * The check of one kind of result
     *
     * @param OutputCheckInterface $check
     * @param string $code
     * @return FeatureCheckInterface
     */
    private function feature(OutputCheckInterface $check, string $code): FeatureCheckInterface
    {
        foreach ($check->getFeatures() as $feature) {
            if ($feature->getRule()->getCode() === $code) {
                return $feature;
            }
        }
        self::fail('The block was not checked against "' . $code . '".');
    }

    /**
     * The rule codes of a check's features, in order
     *
     * @param OutputCheckInterface $check
     * @return list<string>
     */
    private function codes(OutputCheckInterface $check): array
    {
        return array_map(
            static fn (FeatureCheckInterface $f): string => $f->getRule()->getCode(),
            $check->getFeatures()
        );
    }

    /**
     * The paths of missing properties, in order
     *
     * @param list<MissingPropertyInterface> $missing
     * @return list<string>
     */
    private function paths(array $missing): array
    {
        return array_map(static fn (MissingPropertyInterface $m): string => $m->getPath(), $missing);
    }
}
