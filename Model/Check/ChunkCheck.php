<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Check;

use Hryvinskyi\JsonTemplate\Api\RecordingTraceFactoryInterface;
use Hryvinskyi\JsonTemplate\Api\TemplateEngineInterface;
use Hryvinskyi\RichSnippets\Api\Data\OutputCheckInterface;
use Hryvinskyi\RichSnippets\Api\Data\PageEntityInterface;
use Hryvinskyi\RichSnippets\Api\Data\SnippetInterface;
use Hryvinskyi\RichSnippets\Api\PageDataPrefetchInterface;
use Hryvinskyi\RichSnippets\Api\PageScopeFactoryInterface;
use Hryvinskyi\RichSnippets\Api\RequirementCheckerInterface;
use Hryvinskyi\RichSnippets\Api\SnippetSelectorInterface;
use Hryvinskyi\RichSnippets\Model\Selection\CandidatePrefetch;
use Hryvinskyi\RichSnippets\Model\Selection\SelectionIndexInterface;
use Psr\Log\LoggerInterface;

/**
 * Checks one batch of pages of a kind in a store view, as the storefront would build their snippets
 *
 * Everything the kind's candidate snippets read, for their conditions and their templates, is loaded for the whole
 * batch at once; then each page's snippets are selected, evaluated with a recording trace and compared with Google's
 * rules, and the page is counted. What was loaded is dropped after the batch, so a run holds one batch at a time. A
 * snippet that fails on a page is left out there, as on the storefront, and is logged once per run.
 */
class ChunkCheck
{
    /**
     * @var array<string, true> the codes of the snippets logged in this run
     */
    private array $logged = [];

    /**
     * @param SelectionIndexInterface $index
     * @param CandidatePrefetch $prefetch
     * @param SnippetSelectorInterface $selector
     * @param TemplateEngineInterface $engine the snippets' own engine
     * @param PageScopeFactoryInterface $scopes
     * @param RecordingTraceFactoryInterface $traces
     * @param RequirementCheckerInterface $checker
     * @param PageDataPrefetchInterface $data
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly SelectionIndexInterface $index,
        private readonly CandidatePrefetch $prefetch,
        private readonly SnippetSelectorInterface $selector,
        private readonly TemplateEngineInterface $engine,
        private readonly PageScopeFactoryInterface $scopes,
        private readonly RecordingTraceFactoryInterface $traces,
        private readonly RequirementCheckerInterface $checker,
        private readonly PageDataPrefetchInterface $data,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Forget the snippets logged in an earlier run
     *
     * @return void
     */
    public function startRun(): void
    {
        $this->logged = [];
    }

    /**
     * Check a batch of pages and count them in the store view's tally
     *
     * @param string $kind the pages' kind
     * @param int $storeId the pages' store view
     * @param list<PageEntityInterface> $pages
     * @param StoreTally $tally
     * @return void
     */
    public function check(string $kind, int $storeId, array $pages, StoreTally $tally): void
    {
        try {
            $this->prefetch->forPages($pages, $this->index->snippetsFor($kind, $storeId));
            foreach ($pages as $page) {
                $selected = $this->selector->select($page);
                $tally->addPage($page, $selected, $this->blocks($page, $selected));
            }
        } finally {
            $this->data->reset();
        }
    }

    /**
     * The blocks the selected snippets write on a page, each with its check; an empty one writes none
     *
     * @param PageEntityInterface $page
     * @param list<SnippetInterface> $selected
     * @return list<array{SnippetInterface, OutputCheckInterface}>
     */
    private function blocks(PageEntityInterface $page, array $selected): array
    {
        $blocks = [];
        foreach ($selected as $snippet) {
            try {
                $trace = $this->traces->create();
                $value = $this->engine->evaluate($snippet->getTemplate(), $this->scopes->create($page), $trace);
            } catch (\Throwable $e) {
                $this->logFailure($snippet, $e);
                continue;
            }
            if (!is_array($value) || $value === []) {
                continue;
            }
            $blocks[] = [$snippet, $this->checker->checkOutput($snippet->getType(), $value, $trace)];
        }

        return $blocks;
    }

    /**
     * Log why a snippet was left out of a page, unless it was logged earlier in the run
     *
     * @param SnippetInterface $snippet
     * @param \Throwable $failure
     * @return void
     */
    private function logFailure(SnippetInterface $snippet, \Throwable $failure): void
    {
        $code = $snippet->getCode();
        if (isset($this->logged[$code])) {
            return;
        }
        $this->logged[$code] = true;
        $this->logger->error(
            sprintf('Rich snippets: the catalog check left the snippet "%s" out: %s', $code, $failure->getMessage()),
            ['exception' => $failure, 'snippet' => $code]
        );
    }
}
