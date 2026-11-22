<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Api\Data;

/**
 * One schema.org item a page describes with microdata in its HTML, outside any other item
 *
 * @api
 */
interface MicrodataItemInterface
{
    /**
     * The item's schema.org type, without the vocabulary's address (`Product`)
     *
     * @return string
     */
    public function getType(): string;

    /**
     * The names of the item's own properties, each once, in the order the page first writes them (`name`, `offers`)
     *
     * The properties of an item nested in it belong to that item, not to this one.
     *
     * @return list<string>
     */
    public function getProperties(): array;
}
