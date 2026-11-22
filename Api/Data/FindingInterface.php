<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Api\Data;

/**
 * A gap in the shop's data that a catalog check found: one property of one kind of Google result, empty on some
 * pages for the same reason (the same variables read), or a whole optional result left out
 *
 * A finding of every store view counts each page once, whatever the number of store views it was found in.
 *
 * @api
 */
interface FindingInterface
{
    /**
     * The finding's key, stable from run to run for the same gap; letters, digits and `-` only
     *
     * @return string
     */
    public function getKey(): string;

    /**
     * How much the gap matters: bad makes the pages invalid, warn is a recommended value, note needs nothing
     *
     * @return PropertyTone
     */
    public function getTone(): PropertyTone;

    /**
     * The code of the kind of Google result that misses something
     *
     * @return string
     */
    public function getRuleCode(): string;

    /**
     * The missing property's path from the block's root; empty when the whole result is left out
     *
     * @return string
     */
    public function getPath(): string;

    /**
     * The variable paths the template read for the property and found empty (`product.ean`)
     *
     * None when the template never writes the property.
     *
     * @return list<string>
     */
    public function getSources(): array;

    /**
     * The codes of the snippets whose blocks have the gap
     *
     * @return list<string>
     */
    public function getSnippetCodes(): array;

    /**
     * The pages with the gap
     *
     * @return int
     */
    public function getPages(): int;

    /**
     * The pages with the gap by kind of page (`product` => 23)
     *
     * @return array<string,int>
     */
    public function getPagesByKind(): array;

    /**
     * Whether the whole result is left out, as an optional result is on a page with nothing for it
     *
     * @return bool
     */
    public function isResultLeftOut(): bool;
}
