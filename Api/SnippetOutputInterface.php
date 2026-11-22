<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Api;

use Hryvinskyi\RichSnippets\Api\Data\PageEntityInterface;
use Hryvinskyi\RichSnippets\Api\Data\RenderedBlockInterface;

/**
 * The script blocks a page writes: each snippet the page outputs, evaluated and written as JSON
 *
 * Nothing is written while the snippets are switched off for the page's store view in the settings. A snippet whose
 * value is empty writes nothing. A snippet that fails to compile or evaluate is logged, once per request, and left
 * out; the others are still written. The JSON is compact or readable as the store view's format setting says.
 *
 * Evaluation reads the current area and visitor context (customer group, display currency): on the storefront that
 * is the visitor's; anywhere else call it inside FrontendEmulationInterface::run().
 *
 * @api
 */
interface SnippetOutputInterface
{
    /**
     * The blocks of a page, in the order of its snippets
     *
     * @param PageEntityInterface $page
     * @return list<RenderedBlockInterface>
     */
    public function forPage(PageEntityInterface $page): array;
}
