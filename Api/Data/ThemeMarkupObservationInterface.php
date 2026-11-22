<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Api\Data;

/**
 * What a page test last saw of the theme's own schema.org microdata on a kind of page of a store view
 *
 * @api
 */
interface ThemeMarkupObservationInterface
{
    /**
     * The store view the page belongs to
     *
     * @return int
     */
    public function getStoreId(): int;

    /**
     * The code of the page's kind (`product`)
     *
     * @return string
     */
    public function getKind(): string;

    /**
     * The path of the page that was read, from the store view's base address on (`/bags/field-satchel.html`)
     *
     * @return string
     */
    public function getPath(): string;

    /**
     * Whether the page carried any schema.org microdata
     *
     * @return bool
     */
    public function isFound(): bool;

    /**
     * The items the microdata described, in page order; none when nothing was found
     *
     * @return list<MicrodataItemInterface>
     */
    public function getItems(): array;

    /**
     * When the page was read, in Unix seconds
     *
     * @return int
     */
    public function getSeenAt(): int;
}
