<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Api\Data;

use Hryvinskyi\JsonTemplate\Api\Data\TraceOutcome;

/**
 * A property the template writes that has no value on a page, so it is left out of the block
 *
 * @api
 */
interface LeftOutPropertyInterface
{
    /**
     * The property's path from the block's root, list indexes dropped (`offers.priceValidUntil`)
     *
     * @return string
     */
    public function getPath(): string;

    /**
     * Why it is left out: its value is empty, or its condition is false
     *
     * @return TraceOutcome
     */
    public function getOutcome(): TraceOutcome;

    /**
     * The variable paths the template read for it (`product.ean`)
     *
     * @return list<string>
     */
    public function getSources(): array;
}
