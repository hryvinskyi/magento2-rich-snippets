<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Api\Data;

/**
 * A store value a template can read by its variable path, as the field picker lists it
 *
 * A field of a root (`product.name`) has a value for the entity the root stands for on a page; a field of a list
 * item (`review.nickname`) is carried by each item of its list.
 *
 * @api
 */
interface FieldInterface
{
    /**
     * The dot-separated variable path (`product.manufacturer`, `store.address.city`)
     *
     * @return string
     */
    public function getPath(): string;

    /**
     * What the field picker shows (`Manufacturer`)
     *
     * @return string
     */
    public function getLabel(): string;

    /**
     * The code of the group the field is listed in (`product`, `product_attributes`, `price` …)
     *
     * @return string
     */
    public function getGroupCode(): string;

    /**
     * What the field picker shows for the group (`Price and stock`)
     *
     * @return string
     */
    public function getGroupLabel(): string;

    /**
     * The catalog attribute the field reads; null when it reads none
     *
     * @return string|null
     */
    public function getAttributeCode(): ?string;

    /**
     * The kinds of page on which the field has a value
     *
     * @return list<string> none: every kind of page
     */
    public function getPageKinds(): array;
}
