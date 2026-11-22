<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Admin\Test\Summary;

use Hryvinskyi\AdminUi\Api\Page\PluralPhrasesInterface;

/**
 * A product as a merchant listing, with its rating and how many reviews it carries
 *
 * One review is "a review"; from two on, the number in the plural form the admin's language gives it.
 */
class ProductSummary implements BlockSummaryInterface
{
    /**
     * @param PluralPhrasesInterface $plurals
     */
    public function __construct(private readonly PluralPhrasesInterface $plurals)
    {
    }

    /**
     * @inheritDoc
     */
    public function summarize(array $object): string
    {
        $rating = $object['aggregateRating'] ?? null;
        if (!is_array($rating) || $rating === []) {
            return (string) __('A merchant listing with no rating yet.');
        }
        $reviews = $object['review'] ?? [];
        $count = match (true) {
            !is_array($reviews) || $reviews === [] => 0,
            array_is_list($reviews) => count($reviews),
            default => 1,
        };

        if ($count < 2) {
            return (string) ($count === 0
                ? __('A merchant listing with rating.')
                : __('A merchant listing with rating and a review.'));
        }

        return $this->plurals->sentence(
            $count,
            __('A merchant listing with rating and %1 review.', $count),
            __('A merchant listing with rating and %1 reviews.', $count)
        );
    }
}
