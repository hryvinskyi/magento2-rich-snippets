<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Api;

/**
 * Turns a catalog attribute's stored value into the value a template reads, for one kind of input
 *
 * Registered by the attribute's input type (`select`, `multiselect`, `boolean`, `date`, `media_image`, `price` …):
 * a select yields its option's label, a multiselect the list of labels, a boolean a bool, a date ISO 8601, an image
 * an absolute URL, a price a string at the currency's precision.
 *
 * @api
 */
interface AttributeValueResolverInterface
{
    /**
     * The value a template reads
     *
     * @param mixed $value the stored value; null when the entity has none
     * @param array<int|string,string> $optionLabels the labels of the attribute's options in the store view, by
     *     stored option value, for the values this entity holds
     * @param int $storeId
     * @return mixed null when there is nothing to write
     */
    public function resolve(mixed $value, array $optionLabels, int $storeId): mixed;
}
