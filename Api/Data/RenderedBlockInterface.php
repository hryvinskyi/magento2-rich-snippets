<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Api\Data;

/**
 * One snippet as a page writes it: the snippet's code and type, and the JSON of its script block
 *
 * The JSON is already safe inside an HTML script element: `<`, `>` and `&` are written as Unicode escapes, so no
 * text a template reads can end the element or open a comment.
 *
 * @api
 */
interface RenderedBlockInterface
{
    /**
     * The media type of the script element a block is written in
     */
    public const MEDIA_TYPE = 'application/ld+json';

    /**
     * The attribute of the script element that carries the snippet's code
     */
    public const CODE_ATTRIBUTE = 'data-snippet';

    /**
     * The code of the snippet the block was built from
     *
     * @return string
     */
    public function getCode(): string;

    /**
     * The snippet's schema.org type: the first type of its template's root
     *
     * @return string
     */
    public function getType(): string;

    /**
     * The JSON text written inside the script element
     *
     * @return string
     */
    public function getJson(): string;
}
