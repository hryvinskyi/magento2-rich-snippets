<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Editor;

use Hryvinskyi\RichSnippets\Api\Data\SnippetInterface;
use Hryvinskyi\RichSnippets\Api\Exception\MalformedLibraryTemplateException;
use Hryvinskyi\RichSnippets\Api\LibraryTemplatePoolInterface;
use Hryvinskyi\RichSnippets\Api\SnippetRepositoryInterface;
use Hryvinskyi\RichSnippets\Model\Snippet\IncludeEdgesInterface;
use Hryvinskyi\RichSnippets\Model\Snippet\IncludeGraph;

/**
 * The snippets a snippet can include, every page snippet and building block but itself, each flagged when it
 * already contains the snippet
 *
 * A candidate that includes the snippet, directly or through others, would close a loop, which saving refuses: it is
 * listed so the editor can say why it cannot be picked. A snippet that is not saved yet is contained by none. A
 * candidate added from the library is described by its template's description.
 */
class IncludeCandidates
{
    /**
     * @param SnippetRepositoryInterface $snippets
     * @param IncludeGraph $graph
     * @param IncludeEdgesInterface $edges
     * @param LibraryTemplatePoolInterface $library
     */
    public function __construct(
        private readonly SnippetRepositoryInterface $snippets,
        private readonly IncludeGraph $graph,
        private readonly IncludeEdgesInterface $edges,
        private readonly LibraryTemplatePoolInterface $library
    ) {
    }

    /**
     * The candidates for a snippet, by sort order, then ID
     *
     * @param string|null $code the snippet's code; null for a snippet not saved yet
     * @return list<array{snippet: SnippetInterface, loops: bool, description: string}>
     * @throws MalformedLibraryTemplateException
     */
    public function of(?string $code): array
    {
        $edges = $code === null ? [] : $this->edges->edges();
        $candidates = [];
        foreach ($this->snippets->list() as $snippet) {
            if ($snippet->getCode() === $code) {
                continue;
            }
            $candidates[] = [
                'snippet' => $snippet,
                'loops' => $code !== null && $this->graph->loopThrough($code, [$snippet->getCode()], $edges) !== [],
                'description' => $this->description($snippet),
            ];
        }

        return $candidates;
    }

    /**
     * The description of the library template a snippet was added from; empty for one written in the shop
     *
     * @param SnippetInterface $snippet
     * @return string
     * @throws MalformedLibraryTemplateException
     */
    private function description(SnippetInterface $snippet): string
    {
        $origin = $snippet->getOrigin();

        return $origin === null ? '' : (string) $this->library->get($origin->getCode())?->getDescription();
    }
}
