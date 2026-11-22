<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Api\Data;

/**
 * One short remark on a snippet's row in the list: "offers.price is missing: Google needs it for a merchant listing"
 *
 * @api
 */
interface SnippetFlagInterface
{
    /**
     * How much the remark matters
     *
     * @return FlagTone
     */
    public function getTone(): FlagTone;

    /**
     * The remark, translated, short enough for a badge
     *
     * @return string
     */
    public function getText(): string;
}
