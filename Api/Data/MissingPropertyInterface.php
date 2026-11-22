<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Api\Data;

/**
 * A property a kind of Google result asks for that a block does not carry
 *
 * @api
 */
interface MissingPropertyInterface
{
    /**
     * The code of the rule that asks for the property
     *
     * @return string
     */
    public function getRuleCode(): string;

    /**
     * The property's path from the block's root (`offers.price`, `hasMerchantReturnPolicy.returnFees`)
     *
     * @return string
     */
    public function getPath(): string;

    /**
     * How much its absence matters
     *
     * @return PropertyTone
     */
    public function getTone(): PropertyTone;

    /**
     * The variable paths the template read for it and found empty (`product.ean`)
     *
     * Known only when the block was evaluated with a recording trace; empty otherwise, and when the template never
     * wrote the property.
     *
     * @return list<string>
     */
    public function getSources(): array;
}
