<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Selection;

use Hryvinskyi\JsonTemplate\Api\TemplateEngineInterface;
use Hryvinskyi\RichSnippets\Api\Data\PageEntityInterface;
use Hryvinskyi\RichSnippets\Api\Data\SnippetInterface;
use Hryvinskyi\RichSnippets\Model\Engine\TemplatePrefetch;
use Hryvinskyi\RichSnippets\Model\Placement\PlacementConditions;

/**
 * Names a page, with everything its candidate snippets' conditions and templates read, to the data sources before
 * the conditions are evaluated
 *
 * So the values a condition reads come in the same batch as the templates' data: evaluating conditions costs no
 * query of its own, and writing the selected snippets afterwards loads nothing again. A template that does not
 * compile adds nothing; it is left out when the snippets are written. Naming is never what breaks a page: when it
 * fails, every value is still read, one at a time.
 */
class CandidatePrefetch
{
    /**
     * @param TemplateEngineInterface $engine the snippets' own engine
     * @param TemplatePrefetch $prefetch
     * @param PlacementConditions $conditions
     */
    public function __construct(
        private readonly TemplateEngineInterface $engine,
        private readonly TemplatePrefetch $prefetch,
        private readonly PlacementConditions $conditions
    ) {
    }

    /**
     * Name the page and what the candidates read
     *
     * @param PageEntityInterface $page
     * @param list<SnippetInterface> $candidates
     * @return void
     */
    public function forCandidates(PageEntityInterface $page, array $candidates): void
    {
        $this->forPages([$page], $candidates);
    }

    /**
     * Name a batch of pages and what the candidates read, in one go
     *
     * Asked again for one of the pages with the same candidates, the sources load nothing more.
     *
     * @param list<PageEntityInterface> $pages
     * @param list<SnippetInterface> $candidates
     * @return void
     */
    public function forPages(array $pages, array $candidates): void
    {
        if ($candidates === [] || $pages === []) {
            return;
        }
        $templates = [];
        $paths = [];
        foreach ($candidates as $snippet) {
            array_push($paths, ...$this->conditions->variablePaths($snippet->getConditions()));
            try {
                $templates[] = $this->engine->compile($snippet->getTemplate());
            } catch (\Throwable) {
                continue;
            }
        }
        try {
            $this->prefetch->forPages($pages, $templates, array_values(array_unique($paths)));
        } catch (\Throwable) {
            return;
        }
    }
}
