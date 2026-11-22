<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Draft;

use Hryvinskyi\JsonTemplate\Api\Exception\CompileException;
use Hryvinskyi\JsonTemplate\Api\TemplateEngineInterface;
use Hryvinskyi\RichSnippets\Api\Data\DraftProblemInterface;
use Hryvinskyi\RichSnippets\Api\Data\DraftSnippetInterface;
use Hryvinskyi\RichSnippets\Model\Snippet\IncludeEdgesInterface;
use Hryvinskyi\RichSnippets\Model\Snippet\IncludeGraph;
use Hryvinskyi\RichSnippets\Model\Snippet\SnippetRootInterface;

/**
 * Everything wrong with a draft written into a snippet: the template's errors, a loop its includes would close, the
 * values the snippet rules refuse, and a page snippet output nowhere
 *
 * The template comes first, so the editor marks the code before anything else. A loop is looked for only when the
 * snippet has its real code: no stored snippet can include a snippet that is not saved yet.
 */
class DraftValidation
{
    /**
     * @param TemplateEngineInterface $engine the snippets' own engine
     * @param DraftApplication $application
     * @param DraftProblems $problems
     * @param IncludeGraph $graph
     * @param IncludeEdgesInterface $edges
     * @param DraftRoots $roots
     */
    public function __construct(
        private readonly TemplateEngineInterface $engine,
        private readonly DraftApplication $application,
        private readonly DraftProblems $problems,
        private readonly IncludeGraph $graph,
        private readonly IncludeEdgesInterface $edges,
        private readonly DraftRoots $roots
    ) {
    }

    /**
     * Write a draft into a snippet and list everything wrong with it
     *
     * @param SnippetRootInterface $snippet changed in place
     * @param DraftSnippetInterface $draft
     * @return list<DraftProblemInterface>
     */
    public function check(SnippetRootInterface $snippet, DraftSnippetInterface $draft): array
    {
        $refused = $this->application->apply($snippet, $draft);
        $template = [];
        try {
            $includes = $this->engine->compile($snippet->getTemplate())->getIncludes();
            $loop = $this->roots->isStandIn($snippet)
                ? []
                : $this->graph->loopThrough($snippet->getCode(), $includes, $this->edges->edges());
            if ($loop !== []) {
                $template[] = $this->problems->includeLoop($loop);
            }
        } catch (CompileException $e) {
            $template = $this->problems->fromCompile($e);
        }
        $problems = array_merge($template, $refused);
        if (!$snippet->isBlock() && $snippet->getPageKinds() === [] && !$this->concerns($problems)) {
            $problems[] = $this->problems->noPageKinds();
        }

        return $problems;
    }

    /**
     * The problems that stop a draft from being saved
     *
     * @param list<DraftProblemInterface> $problems
     * @return list<DraftProblemInterface>
     */
    public function blocking(array $problems): array
    {
        return array_values(array_filter(
            $problems,
            static fn (DraftProblemInterface $problem): bool => $problem->isBlocking()
        ));
    }

    /**
     * Whether a problem already concerns the kinds of page
     *
     * @param list<DraftProblemInterface> $problems
     * @return bool
     */
    private function concerns(array $problems): bool
    {
        foreach ($problems as $problem) {
            if ($problem->getPart() === DraftProblemInterface::PART_PAGE_KINDS) {
                return true;
            }
        }

        return false;
    }
}
