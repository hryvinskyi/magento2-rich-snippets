<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Test\Unit\Model\Requirement;

use Hryvinskyi\JsonTemplate\Api\Data\TraceOutcome;
use Hryvinskyi\RichSnippets\Test\Unit\Fake\RecordingTraceFake;

/**
 * Evaluated blocks shaped like the pages of the admin's previews: a product that has everything (the satchel), one
 * with no EAN and no reviews (the sleeve), one with no image (the care kit), and the home page's organization and
 * website name
 */
class DesignPreviews
{
    /**
     * The satchel's Product block: everything but delivery details
     *
     * @return array<string, mixed>
     */
    public static function satchel(): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => 'Field satchel',
            'description' => 'A waxed canvas satchel with a leather strap.',
            'sku' => 'LC-FS-TAN',
            'gtin13' => '4006381333931',
            'image' => ['https://shop.example/media/satchel-1.jpg', 'https://shop.example/media/satchel-2.jpg'],
            'brand' => ['@type' => 'Brand', 'name' => 'Linden & Co.'],
            'color' => 'Tan',
            'offers' => self::offer() + ['priceValidUntil' => '2026-12-31'],
            'aggregateRating' => ['@type' => 'AggregateRating', 'ratingValue' => 4.6, 'reviewCount' => 12],
            'review' => [
                ['@type' => 'Review', 'author' => ['@type' => 'Person', 'name' => 'Mara'], 'reviewBody' => 'Sturdy.'],
                ['@type' => 'Review', 'author' => ['@type' => 'Person', 'name' => 'Jonas'], 'reviewBody' => 'Fine.'],
            ],
        ];
    }

    /**
     * The sleeve's Product block: no EAN, no reviews, no sale end date
     *
     * @return array<string, mixed>
     */
    public static function sleeve(): array
    {
        $sleeve = self::satchel();
        unset($sleeve['gtin13'], $sleeve['aggregateRating'], $sleeve['review']);
        $sleeve['offers'] = self::offer();
        $sleeve['name'] = 'Card sleeve';
        $sleeve['sku'] = 'LC-CS-BLK';

        return $sleeve;
    }

    /**
     * The trace of the sleeve's evaluation, in the order the nodes finished
     *
     * @return RecordingTraceFake
     */
    public static function sleeveTrace(): RecordingTraceFake
    {
        $trace = new RecordingTraceFake();
        $trace->record('$.name', TraceOutcome::Value, []);
        $trace->record('$.gtin13', TraceOutcome::Empty, ['product.ean']);
        $trace->record('$.offers.priceValidUntil', TraceOutcome::Skipped, ['product.special_to_date']);
        $trace->record('$.offers', TraceOutcome::Value, []);
        $trace->record('$.aggregateRating.ratingValue', TraceOutcome::Empty, ['reviews.rating']);
        $trace->record('$.aggregateRating.reviewCount', TraceOutcome::Empty, ['reviews.count']);
        $trace->record('$.aggregateRating', TraceOutcome::Empty, []);
        $trace->record('$.review', TraceOutcome::Empty, ['reviews.latest']);
        $trace->record('$', TraceOutcome::Value, []);

        return $trace;
    }

    /**
     * The care kit's Product block: no image, no reviews
     *
     * @return array<string, mixed>
     */
    public static function kit(): array
    {
        $kit = self::sleeve();
        unset($kit['image']);
        $kit['name'] = 'Leather care kit';
        $kit['sku'] = 'LC-CARE-01';

        return $kit;
    }

    /**
     * The satchel's breadcrumbs
     *
     * @return array<string, mixed>
     */
    public static function breadcrumbs(): array
    {
        $crumbs = [];
        foreach ([['Home', '/'], ['Bags', '/bags.html'], ['Field satchel', '/bags/field-satchel.html']] as $i => $c) {
            $crumbs[] = [
                '@type' => 'ListItem',
                'position' => $i + 1,
                'name' => $c[0],
                'item' => 'https://shop.example' . $c[1],
            ];
        }

        return ['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => $crumbs];
    }

    /**
     * The home page's Organization block, with its return policy and no shipping service
     *
     * @return array<string, mixed>
     */
    public static function organization(): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'OnlineStore',
            'name' => 'Linden & Co.',
            'url' => 'https://shop.example/',
            'logo' => 'https://shop.example/media/logo.png',
            'email' => 'hello@linden-co.example',
            'telephone' => '+49 30 1234567',
            'address' => [
                '@type' => 'PostalAddress',
                'streetAddress' => 'Lindenstraße 14',
                'postalCode' => '10969',
                'addressLocality' => 'Berlin',
                'addressCountry' => 'DE',
            ],
            'sameAs' => ['https://social.example/lindenco', 'https://video.example/@lindenco'],
            'hasMerchantReturnPolicy' => self::returnPolicy(),
        ];
    }

    /**
     * The home page's WebSite block
     *
     * @return array<string, mixed>
     */
    public static function website(): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'WebSite',
            'name' => 'Linden & Co.',
            'alternateName' => 'Linden',
            'url' => 'https://shop.example/',
        ];
    }

    /**
     * A product's offer, with the shop's return policy and no delivery details
     *
     * @return array<string, mixed>
     */
    public static function offer(): array
    {
        return [
            '@type' => 'Offer',
            'url' => 'https://shop.example/bags/field-satchel.html',
            'price' => '240.00',
            'priceCurrency' => 'EUR',
            'availability' => 'https://schema.org/InStock',
            'itemCondition' => 'https://schema.org/NewCondition',
            'hasMerchantReturnPolicy' => self::returnPolicy(),
        ];
    }

    /**
     * The shop's return policy: 30 days, by mail, free
     *
     * @return array<string, mixed>
     */
    public static function returnPolicy(): array
    {
        return [
            '@type' => 'MerchantReturnPolicy',
            'applicableCountry' => ['DE', 'AT'],
            'returnPolicyCategory' => 'https://schema.org/MerchantReturnFiniteReturnWindow',
            'merchantReturnDays' => 30,
            'returnMethod' => 'https://schema.org/ReturnByMail',
            'returnFees' => 'https://schema.org/FreeReturn',
        ];
    }
}
