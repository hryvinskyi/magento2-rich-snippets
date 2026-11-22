<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Api;

use Hryvinskyi\RichSnippets\Api\Data\PageEntityInterface;

/**
 * A list a template repeats an object over with `@each` (`product.gallery`, each item read as `image`)
 *
 * Lists are registered in the collection pool by path, so another module adds one without changing this module.
 *
 * @api
 */
interface CollectionInterface
{
    /**
     * The dot-separated path the list is read by
     *
     * @return string
     */
    public function getPath(): string;

    /**
     * The name each item is read by when the repeated object has no `@as`
     *
     * @return string
     */
    public function getItemName(): string;

    /**
     * What the field picker shows for the list (`Product › Gallery image`)
     *
     * @return string
     */
    public function getLabel(): string;

    /**
     * The list's items on a page
     *
     * @param PageEntityInterface $page
     * @param int $subjectId the entity the list's root stands for
     * @return list<array<string,mixed>> each item's values by field name
     */
    public function items(PageEntityInterface $page, int $subjectId): array;
}
