<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Storefront;

use Hryvinskyi\RichSnippets\Api\Data\RenderedBlockInterface;

/**
 * Writes a page's script blocks at one place of the page
 *
 * Each block's script element carries the snippet's code in its RenderedBlockInterface::CODE_ATTRIBUTE attribute.
 */
interface OutputPositionInterface
{
    /**
     * Write the blocks; the answer is what the storefront block prints where it stands, at the end of the body
     *
     * @param list<RenderedBlockInterface> $blocks
     * @param bool $marked whether each block is preceded by a comment naming its snippet's code, where the position
     *     can write one
     * @return string
     */
    public function write(array $blocks, bool $marked): string;
}
