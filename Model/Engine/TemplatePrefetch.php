<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Engine;

use Hryvinskyi\JsonTemplate\Api\Data\CompiledTemplateInterface;
use Hryvinskyi\RichSnippets\Api\CollectionLimitsInterface;
use Hryvinskyi\RichSnippets\Api\Data\PageEntityInterface;
use Hryvinskyi\RichSnippets\Api\PageDataPrefetchInterface;

/**
 * Names a page and everything some templates and the templates they include read to the data sources, in one batch
 *
 * Without it every value is still read, one at a time; with it a page's data comes in one query per concern, and a
 * list takes only as many items as the most any template asks for, so a variant's extra attributes and the newest
 * reviews come with the rest. Whatever evaluates templates for a page (the storefront, the editor's preview) calls
 * this before it evaluates; the catalog check names a whole batch of pages at once.
 */
class TemplatePrefetch
{
    /**
     * @param IncludedTemplates $includedTemplates
     * @param CollectionLimitsInterface $collectionLimits
     * @param PageDataPrefetchInterface $prefetch
     */
    public function __construct(
        private readonly IncludedTemplates $includedTemplates,
        private readonly CollectionLimitsInterface $collectionLimits,
        private readonly PageDataPrefetchInterface $prefetch
    ) {
    }

    /**
     * Name a page and what the templates read to the data sources, with any other paths the caller reads
     *
     * @param PageEntityInterface $page
     * @param list<CompiledTemplateInterface> $templates
     * @param list<string> $otherPaths paths read besides the templates' (what a snippet's conditions read)
     * @return void
     */
    public function forPage(PageEntityInterface $page, array $templates, array $otherPaths = []): void
    {
        $this->forPages([$page], $templates, $otherPaths);
    }

    /**
     * Name a batch of pages and what the templates read to the data sources, in one go
     *
     * @param list<PageEntityInterface> $pages
     * @param list<CompiledTemplateInterface> $templates
     * @param list<string> $otherPaths paths read besides the templates' (what a snippet's conditions read)
     * @return void
     */
    public function forPages(array $pages, array $templates, array $otherPaths = []): void
    {
        $reached = $this->includedTemplates->withIncludes($templates);
        $paths = array_fill_keys($otherPaths, true);
        foreach ($reached as $template) {
            foreach ($template->getPaths() as $path) {
                $paths[$path] = true;
            }
        }
        $this->prefetch->prefetch(
            $pages,
            array_map('strval', array_keys($paths)),
            $this->collectionLimits->limits($reached)
        );
    }
}
