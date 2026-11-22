<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Wording;

use Hryvinskyi\AdminUi\Api\Page\PluralPhrasesInterface;

/**
 * A number of pages or of products in words, in the plural form the admin's language gives the number: "1 page",
 * "184 pages", "2 products are invalid"
 */
class CountWords
{
    /**
     * @param PluralPhrasesInterface $plurals
     */
    public function __construct(private readonly PluralPhrasesInterface $plurals)
    {
    }

    /**
     * A number of pages
     *
     * @param int $count
     * @return string
     */
    public function pages(int $count): string
    {
        return $this->plurals->sentence($count, __('%1 page', $count), __('%1 pages', $count));
    }

    /**
     * A number of products
     *
     * @param int $count
     * @return string
     */
    public function products(int $count): string
    {
        return $this->plurals->sentence($count, __('%1 product', $count), __('%1 products', $count));
    }

    /**
     * That a number of pages are invalid, counted as products when every one of them is a product page
     *
     * @param int $count
     * @param bool $products whether every invalid page is a product page
     * @return string
     */
    public function invalid(int $count, bool $products): string
    {
        return $products
            ? $this->plurals->sentence(
                $count,
                __('%1 product is invalid', $count),
                __('%1 products are invalid', $count)
            )
            : $this->plurals->sentence($count, __('%1 page is invalid', $count), __('%1 pages are invalid', $count));
    }
}
