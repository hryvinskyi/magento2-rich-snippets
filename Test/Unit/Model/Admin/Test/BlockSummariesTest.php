<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Test\Unit\Model\Admin\Test;

use Hryvinskyi\RichSnippets\Model\Admin\Test\Summary\BlockSummaries;
use Hryvinskyi\RichSnippets\Model\Admin\Test\Summary\BreadcrumbSummary;
use Hryvinskyi\RichSnippets\Model\Admin\Test\Summary\OrganizationSummary;
use Hryvinskyi\RichSnippets\Model\Admin\Test\Summary\ProductSummary;
use Hryvinskyi\RichSnippets\Model\Admin\Test\Summary\WebSiteSummary;
use Hryvinskyi\RichSnippets\Test\Unit\Fake\DictionaryRenderer;
use Hryvinskyi\RichSnippets\Test\Unit\Fake\EnglishPlurals;
use Hryvinskyi\RichSnippets\Test\Unit\Model\Requirement\DesignPreviews;
use Magento\Framework\Phrase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Each block row of the page test says in one sentence what its block describes, as the design words it
 */
class BlockSummariesTest extends TestCase
{
    public function testAProductIsAMerchantListingWithItsRatingAndReviews(): void
    {
        $summaries = $this->summaries();
        $oneReview = DesignPreviews::satchel();
        $oneReview['review'] = ['@type' => 'Review', 'reviewBody' => 'Sturdy.'];
        $ratedOnly = DesignPreviews::satchel();
        unset($ratedOnly['review']);

        self::assertSame(
            'A merchant listing with rating and 2 reviews.',
            $summaries->of('Product', DesignPreviews::satchel())
        );
        self::assertSame('A merchant listing with rating and a review.', $summaries->of('Product', $oneReview));
        self::assertSame('A merchant listing with rating.', $summaries->of('Product', $ratedOnly));
        self::assertSame('A merchant listing with no rating yet.', $summaries->of('Product', DesignPreviews::sleeve()));
    }

    public function testBreadcrumbsAreTheirNamesInTheOrderOfTheirPositions(): void
    {
        $crumbs = ['@type' => 'BreadcrumbList', 'itemListElement' => [
            ['@type' => 'ListItem', 'position' => 5],
            ['@type' => 'ListItem', 'position' => 3, 'name' => 'Field satchel'],
            ['@type' => 'ListItem', 'position' => 4, 'item' => ['name' => 'Strap']],
            ['@type' => 'ListItem', 'position' => 2, 'name' => 'Bags'],
            ['@type' => 'ListItem', 'position' => 1, 'name' => 'Home'],
        ]];

        self::assertSame(
            'Home › Bags › Field satchel › Strap',
            $this->summaries()->of('BreadcrumbList', $crumbs)
        );
        self::assertSame(
            'Home › Bags › Field satchel',
            $this->summaries()->of('BreadcrumbList', DesignPreviews::breadcrumbs())
        );
        self::assertSame('', $this->summaries()->of('BreadcrumbList', ['itemListElement' => 'none']));
    }

    public function testAShopSaysWhatItCarries(): void
    {
        $summaries = $this->summaries();
        $bare = ['@type' => 'Organization', 'name' => 'Linden & Co.'];
        $many = ['sameAs' => array_fill(0, 7, 'https://social.example/x')] + $bare;

        self::assertSame(
            'The shop, with its address, two profiles and the return policy.',
            $summaries->of('OnlineStore', DesignPreviews::organization())
        );
        self::assertSame('The organization.', $summaries->of('Organization', $bare));
        self::assertSame('The organization, with 7 profiles.', $summaries->of('Organization', $many));
        self::assertSame(
            'The organization, with one profile.',
            $summaries->of('Organization', ['sameAs' => 'https://social.example/x'] + $bare)
        );
    }

    /**
     * Each combination of what an organization carries is one sentence of its own
     *
     * @param array<string, mixed> $carried
     * @param string $sentence
     * @return void
     */
    #[DataProvider('combinations')]
    public function testEachCombinationIsOneSentence(array $carried, string $sentence): void
    {
        $object = ['@type' => 'OnlineStore', 'name' => 'Linden & Co.'] + $carried;

        self::assertSame($sentence, $this->summaries()->of('OnlineStore', $object));
    }

    /**
     * @return array<string, array{array<string, mixed>, string}>
     */
    public static function combinations(): array
    {
        $address = ['address' => ['streetAddress' => 'Linden 1']];
        $profiles = ['sameAs' => ['https://social.example/a', 'https://social.example/b']];
        $returns = ['hasMerchantReturnPolicy' => ['returnPolicyCategory' => 'Finite']];

        return [
            'nothing' => [[], 'The shop.'],
            'address' => [$address, 'The shop, with its address.'],
            'profiles' => [$profiles, 'The shop, with two profiles.'],
            'return policy' => [$returns, 'The shop, with the return policy.'],
            'address and profiles' => [$address + $profiles, 'The shop, with its address and two profiles.'],
            'address and return policy' => [
                $address + $returns,
                'The shop, with its address and the return policy.',
            ],
            'profiles and return policy' => [
                $profiles + $returns,
                'The shop, with two profiles and the return policy.',
            ],
            'all three' => [
                $address + $profiles + $returns,
                'The shop, with its address, two profiles and the return policy.',
            ],
        ];
    }

    /**
     * A language words a combination as a whole: the translation of the sentence decides its order, and only the
     * subject and the profiles are filled in
     *
     * @return void
     */
    public function testATranslationWordsTheWholeSentence(): void
    {
        $renderer = Phrase::getRenderer();
        Phrase::setRenderer(new DictionaryRenderer([
            'The shop' => 'Магазин',
            'two profiles' => 'два профілі',
            '%1, with its address, %2 and the return policy.' => '%1: адреса, %2 і правила повернення.',
        ]));
        try {
            $summary = $this->summaries()->of('OnlineStore', [
                'address' => ['streetAddress' => 'Linden 1'],
                'sameAs' => ['https://social.example/a', 'https://social.example/b'],
                'hasMerchantReturnPolicy' => ['returnPolicyCategory' => 'Finite'],
            ]);
        } finally {
            Phrase::setRenderer($renderer);
        }

        self::assertSame('Магазин: адреса, два профілі і правила повернення.', $summary);
    }

    public function testAWebSiteIsItsName(): void
    {
        self::assertSame('The name of the site: Linden & Co..', $this->summaries()->of('WebSite', [
            '@type' => 'WebSite',
            'name' => 'Linden & Co.',
        ]));
        self::assertSame('', $this->summaries()->of('WebSite', ['@type' => 'WebSite']));
    }

    public function testATypeWithoutASummaryHasNone(): void
    {
        self::assertSame('', $this->summaries()->of('FAQPage', ['@type' => 'FAQPage']));
    }

    public function testAnItemThatIsNotASummaryIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new BlockSummaries(['Product' => new \stdClass()]);
    }

    /**
     * The summaries as the container configures them
     *
     * @return BlockSummaries
     */
    private function summaries(): BlockSummaries
    {
        $shop = new OrganizationSummary(new EnglishPlurals(), 'The shop');

        return new BlockSummaries([
            'Product' => new ProductSummary(new EnglishPlurals()),
            'BreadcrumbList' => new BreadcrumbSummary(),
            'WebSite' => new WebSiteSummary(),
            'Organization' => new OrganizationSummary(new EnglishPlurals()),
            'OnlineStore' => $shop,
        ]);
    }
}
