<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Api;

use Hryvinskyi\RichSnippets\Api\Data\PageEntityInterface;
use Hryvinskyi\RichSnippets\Api\Data\SnippetExclusionInterface;
use Hryvinskyi\RichSnippets\Api\Data\SnippetInterface;

/**
 * Which snippets a page outputs, and why the others are not output there
 *
 * A page outputs the page snippets that are switched on, set for its store view (or every store view) and its kind,
 * and whose conditions the page meets. Only the conditions whose field is offered for the page's kind are tested on
 * it: a snippet set for product and CMS pages with a product type condition outputs on every CMS page. No condition
 * of the page's kind means every page of the kind. A condition whose field no longer exists never holds. Building
 * blocks are output only inside the snippets that include them, so neither list has them.
 *
 * @api
 */
interface SnippetSelectorInterface
{
    /**
     * The snippets the page outputs, by sort order, then ID
     *
     * @param PageEntityInterface $page
     * @return list<SnippetInterface>
     */
    public function select(PageEntityInterface $page): array;

    /**
     * Every page snippet the page does not output, by sort order, then ID, each with the reason
     *
     * @param PageEntityInterface $page
     * @return list<SnippetExclusionInterface>
     */
    public function explain(PageEntityInterface $page): array;
}
