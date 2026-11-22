<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Test\Unit\Model\Admin\Editor;

use Hryvinskyi\RichSnippets\Api\Data\PreviewState;
use Hryvinskyi\RichSnippets\Model\Admin\Editor\PreviewChecks;
use Hryvinskyi\RichSnippets\Model\Draft\DraftPreview;
use Hryvinskyi\RichSnippets\Model\Page\PageEntity;
use Hryvinskyi\RichSnippets\Model\Wording\WordList;
use Hryvinskyi\RichSnippets\Test\Unit\Fake\EnglishPlurals;
use Hryvinskyi\RichSnippets\Test\Unit\Fake\ModuleChecker;
use Hryvinskyi\RichSnippets\Test\Unit\Model\Requirement\DesignPreviews;
use PHPUnit\Framework\TestCase;

/**
 * The Checks panel words what Google would say about the block on the sample page, in the design's sentences
 */
class PreviewChecksTest extends TestCase
{
    public function testAMissingObjectIsNamedByTheObjectAndCanBeAddedBack(): void
    {
        $product = DesignPreviews::sleeve();
        unset($product['offers']);

        $row = $this->rows('Product', $product)[0];

        self::assertSame('bad', $row['tone']);
        self::assertSame('Google needs offers for a merchant listing', $row['title']);
        self::assertSame(
            'Required: name, image and an offer with a price and a currency. Without them Search Console reports the'
                . ' page as invalid.',
            $row['text']
        );
        self::assertSame(
            ['label' => 'Add offers', 'path' => 'offers', 'icon' => 'add', 'value' => null, 'before' => []],
            $row['action']
        );
    }

    public function testAPropertyMissingInsideAPresentObjectIsNamedByItsPath(): void
    {
        $product = DesignPreviews::kit();
        $offer = DesignPreviews::offer();
        unset($offer['priceCurrency']);
        $product['offers'] = $offer;

        self::assertSame(
            'Google needs image and offers.priceCurrency for a merchant listing',
            $this->rows('Product', $product)[0]['title']
        );
    }

    public function testAResultGoogleNoLongerShowsSaysSoAndNothingMore(): void
    {
        $rows = $this->rows('FAQPage', ['@type' => 'FAQPage', 'mainEntity' => [['@type' => 'Question']]]);

        self::assertSame(
            [[
                'tone' => 'warn',
                'title' => 'Google no longer shows FAQ results',
                'text' => 'Google stopped showing FAQ results in 2026. Other search engines and assistants can still'
                    . ' read it.',
                'action' => null,
            ]],
            $rows
        );
    }

    public function testATypeWithoutARuleIsOutputAsWritten(): void
    {
        $rows = $this->rows('Event', ['@type' => 'Event', 'name' => 'Open day']);

        self::assertSame('note', $rows[0]['tone']);
        self::assertSame('Google has no result type for Event', $rows[0]['title']);
    }

    public function testTheOneMissingRecommendedPropertyIsDescribedByWhyItsRuleRecommendsIt(): void
    {
        $rows = $this->rows('Product', DesignPreviews::satchel());

        self::assertSame(
            [
                'tone' => 'note',
                'title' => 'Google also recommends offers.shippingDetails',
                'text' => 'Google can use it to show delivery cost and time with the result.',
                'action' => [
                    'label' => 'Add it',
                    'path' => 'offers.shippingDetails',
                    'icon' => 'add',
                    'value' => null,
                    'before' => [],
                ],
            ],
            $rows[1]
        );
    }

    public function testOneMissingRecommendedPropertyWithoutAReasonIsDescribedByItsPath(): void
    {
        $product = DesignPreviews::satchel();
        $product['offers'] = DesignPreviews::offer() + ['shippingDetails' => ['@type' => 'OfferShippingDetails']];
        unset($product['sku']);

        self::assertSame(
            [
                'tone' => 'note',
                'title' => 'Google also recommends sku',
                'text' => 'sku',
                'action' => ['label' => 'Add it', 'path' => 'sku', 'icon' => 'add', 'value' => null, 'before' => []],
            ],
            $this->rows('Product', $product)[1]
        );
    }

    public function testSeveralMissingRecommendedPropertiesAreCountedAndListedByPath(): void
    {
        $product = DesignPreviews::satchel();
        unset($product['sku']);

        self::assertSame(
            [
                'tone' => 'note',
                'title' => 'Google also recommends 2 more properties',
                'text' => 'sku, offers.shippingDetails',
                'action' => null,
            ],
            $this->rows('Product', $product)[1]
        );
    }

    /**
     * The rows of a block written on a sample page
     *
     * @param string $type
     * @param array<string, mixed> $output
     * @return list<array{tone: string, title: string, text: string,
     *     action: array{label: string, path: string|null, icon: string, value: string|null,
     *     before: list<string>}|null}>
     */
    private function rows(string $type, array $output): array
    {
        $check = ModuleChecker::create()->checkOutput($type, $output);
        $preview = new DraftPreview(PreviewState::Output, new PageEntity('product', 1, 1), $output, [], [], [], $check);

        return (new PreviewChecks(new EnglishPlurals(), new WordList()))->rows($preview, $type, 'Card sleeve');
    }
}
