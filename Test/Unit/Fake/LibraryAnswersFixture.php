<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Test\Unit\Fake;

use Hryvinskyi\RichSnippets\Api\Data\AddedSnippetInterface;
use Hryvinskyi\RichSnippets\Api\Exception\SnippetRuleException;
use Hryvinskyi\RichSnippets\Api\Exception\SnippetRuleReason;
use Hryvinskyi\RichSnippets\Model\Admin\Library\LibraryListing;
use Hryvinskyi\RichSnippets\Model\Admin\Library\LibraryQuestions;
use Hryvinskyi\RichSnippets\Model\Admin\Library\LibraryRequest;
use Hryvinskyi\RichSnippets\Model\Admin\Library\LibraryWrites;

/**
 * The Library page's answers in the states its contract files hold (`Test/_contract/admin-pages/library-*.json`),
 * written by the real presenters over the sample shop: the library of a shop with nine templates added and of a
 * first visit; the questions of Product, Organization and Breadcrumbs; and the answers of a first add, an add of a
 * template the shop has, an add of a building block, the recommended set and a refused add
 */
class LibraryAnswersFixture
{
    private const KEY = 'key-1';

    /**
     * The list endpoint's answers, by state
     *
     * @return array<string, mixed>
     */
    public static function lists(): array
    {
        return [
            'lib' => self::listing(LibraryShop::having(LibraryShop::NINE_ADDED))->read(),
            'first' => self::listing(LibraryShop::having([]))->read(),
        ];
    }

    /**
     * The suggestions endpoint's answers, by template
     *
     * @return array<string, mixed>
     */
    public static function suggestions(): array
    {
        $questions = new LibraryQuestions(new LibraryRequest(), LibraryShop::having([]));
        $answers = [];
        foreach (['product', 'organization', 'breadcrumbs'] as $code) {
            $answers[$code] = $questions->of(['template' => $code]);
        }

        return $answers;
    }

    /**
     * The add endpoints' answers, by state
     *
     * @return array<string, mixed>
     */
    public static function adds(): array
    {
        return [
            'added' => self::add(['product', 'return-policy'], 'product', [
                LibraryShop::snippet(41, 'return-policy', 'Return policy', true, false),
                LibraryShop::snippet(42, 'product', 'Product', false, false),
            ]),
            'again' => self::add(LibraryShop::NINE_ADDED, 'product', [
                LibraryShop::snippet(8, 'return-policy', 'Return policy', true, true),
                LibraryShop::snippet(43, 'product-2', 'Product 2', false, false, 'product'),
            ]),
            'block' => self::add(['shipping-policy'], 'shipping-policy', [
                LibraryShop::snippet(44, 'shipping-policy', 'Shipping policy', true, false),
            ]),
            'recommended' => self::writes(LibraryShop::having([
                'organization', 'website-name', 'breadcrumbs', 'product', 'product-variants', 'return-policy',
                'postal-address',
            ])->answering([
                LibraryShop::snippet(41, 'return-policy', 'Return policy', true, false),
                LibraryShop::snippet(42, 'postal-address', 'Postal address', true, false),
                LibraryShop::snippet(43, 'organization', 'Organization', false, false),
                LibraryShop::snippet(44, 'website-name', 'Website name', false, false),
                LibraryShop::snippet(45, 'breadcrumbs', 'Breadcrumbs', false, false),
                LibraryShop::snippet(41, 'return-policy', 'Return policy', true, true),
                LibraryShop::snippet(46, 'product', 'Product', false, false),
                LibraryShop::snippet(41, 'return-policy', 'Return policy', true, true),
                LibraryShop::snippet(47, 'product-variants', 'Product with variants', false, false),
            ]))->addRecommended(['idempotencyKey' => self::KEY]),
            'refused' => self::writes(LibraryShop::having([])->refusing(
                new SnippetRuleException(SnippetRuleReason::IncludeLoop, ['path' => 'product > return-policy'])
            ))->add(['template' => 'product', 'answers' => [], 'idempotencyKey' => self::KEY]),
        ];
    }

    /**
     * The answer of an add in a shop that ends with the templates named
     *
     * @param list<string> $after the templates the shop has after the add
     * @param string $template
     * @param list<AddedSnippetInterface> $added
     * @return array<string, mixed>
     */
    private static function add(array $after, string $template, array $added): array
    {
        return self::writes(LibraryShop::having($after)->answering($added))->add([
            'template' => $template,
            'answers' => ['brand' => 'attribute:manufacturer'],
            'idempotencyKey' => self::KEY,
        ]);
    }

    /**
     * @param LibraryShop $shop
     * @return LibraryWrites
     */
    private static function writes(LibraryShop $shop): LibraryWrites
    {
        return new LibraryWrites(new LibraryRequest(), $shop, self::listing($shop));
    }

    /**
     * @param LibraryShop $shop
     * @return LibraryListing
     */
    private static function listing(LibraryShop $shop): LibraryListing
    {
        return new LibraryListing($shop, LibraryShop::moduleTemplates());
    }
}
