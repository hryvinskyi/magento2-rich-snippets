<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Api\Data;

/**
 * One storefront page as the snippets see it: its kind, the entity it shows and its store view
 *
 * The entity is the product of a product page, the category of a category page and the CMS page of a CMS page or
 * of the home page; a page of a kind that shows no entity has none.
 *
 * @api
 */
interface PageEntityInterface
{
    /**
     * The code of the page's kind (`home`, `product`, `category`, `cms`, `other`)
     *
     * @return string
     */
    public function getKind(): string;

    /**
     * The ID of the entity the page shows; null when it shows none
     *
     * @return int|null
     */
    public function getEntityId(): ?int;

    /**
     * The store view the page belongs to
     *
     * @return int
     */
    public function getStoreId(): int;
}
