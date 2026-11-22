<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Api\Data;

/**
 * One JSON-LD script block found in the HTML of a page
 *
 * @api
 */
interface JsonLdBlockInterface
{
    /**
     * The code of the snippet that wrote the block; null for a block Rich Snippets did not write
     *
     * The code is the script element's `data-snippet` attribute; a block without one was written by something else.
     *
     * @return string|null
     */
    public function getCode(): ?string;

    /**
     * The text inside the script element, as the page carries it
     *
     * @return string
     */
    public function getJson(): string;

    /**
     * The decoded JSON: an object or a list; null when the text is not JSON, or is JSON of a single value
     *
     * @return array<mixed>|null
     */
    public function getValue(): ?array;
}
