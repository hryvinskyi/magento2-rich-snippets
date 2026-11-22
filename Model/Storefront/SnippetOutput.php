<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Storefront;

use Hryvinskyi\JsonTemplate\Api\Data\CompiledTemplateInterface;
use Hryvinskyi\JsonTemplate\Api\JsonEncoderInterface;
use Hryvinskyi\JsonTemplate\Api\TemplateEngineInterface;
use Hryvinskyi\RichSnippets\Api\Data\OutputFormat;
use Hryvinskyi\RichSnippets\Api\Data\PageEntityInterface;
use Hryvinskyi\RichSnippets\Api\Data\RenderedBlockInterface;
use Hryvinskyi\RichSnippets\Api\Data\RenderedBlockInterfaceFactory;
use Hryvinskyi\RichSnippets\Api\Data\SnippetInterface;
use Hryvinskyi\RichSnippets\Api\PageScopeFactoryInterface;
use Hryvinskyi\RichSnippets\Api\SnippetOutputInterface;
use Hryvinskyi\RichSnippets\Api\SnippetSelectorInterface;
use Hryvinskyi\RichSnippets\Model\Config\OutputSettings;
use Hryvinskyi\RichSnippets\Model\Engine\TemplatePrefetch;
use Magento\Framework\ObjectManager\ResetAfterRequestInterface;
use Psr\Log\LoggerInterface;

/**
 * Selects a page's snippets, loads the data every one of them reads in one batch, then evaluates and writes each
 *
 * A page is never broken by a snippet: whatever a snippet throws while it compiles or evaluates is logged and that
 * snippet is left out. A snippet is logged once per request however often its page is asked for. A failure to load
 * the batch is logged too; each value is then read on its own as it is evaluated.
 */
class SnippetOutput implements SnippetOutputInterface, ResetAfterRequestInterface
{
    /**
     * @var array<string, true> the codes of the snippets logged in this request
     */
    private array $logged = [];

    /**
     * @param OutputSettings $settings
     * @param SnippetSelectorInterface $selector
     * @param TemplateEngineInterface $engine the snippets' own engine
     * @param TemplatePrefetch $prefetch
     * @param PageScopeFactoryInterface $scopes
     * @param JsonEncoderInterface $json
     * @param RenderedBlockInterfaceFactory $blockFactory
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly OutputSettings $settings,
        private readonly SnippetSelectorInterface $selector,
        private readonly TemplateEngineInterface $engine,
        private readonly TemplatePrefetch $prefetch,
        private readonly PageScopeFactoryInterface $scopes,
        private readonly JsonEncoderInterface $json,
        private readonly RenderedBlockInterfaceFactory $blockFactory,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @inheritDoc
     */
    public function forPage(PageEntityInterface $page): array
    {
        $storeId = $page->getStoreId();
        if (!$this->settings->isEnabled($storeId)) {
            return [];
        }

        $compiled = $this->compile($this->selector->select($page));
        if ($compiled === []) {
            return [];
        }
        $this->load($page, array_column($compiled, 1));

        $pretty = $this->settings->format($storeId) === OutputFormat::Readable;
        $blocks = [];
        foreach ($compiled as [$snippet]) {
            $block = $this->write($snippet, $page, $pretty);
            if ($block !== null) {
                $blocks[] = $block;
            }
        }

        return $blocks;
    }

    /**
     * @inheritDoc
     */
    public function _resetState(): void
    {
        $this->logged = [];
    }

    /**
     * The snippets whose templates compile, each with its compiled template
     *
     * @param list<SnippetInterface> $snippets
     * @return list<array{SnippetInterface, CompiledTemplateInterface}>
     */
    private function compile(array $snippets): array
    {
        $compiled = [];
        foreach ($snippets as $snippet) {
            try {
                $compiled[] = [$snippet, $this->engine->compile($snippet->getTemplate())];
            } catch (\Throwable $e) {
                $this->logFailure($snippet, $e);
            }
        }

        return $compiled;
    }

    /**
     * Name the page and everything the templates and their includes read to the data sources, in one batch
     *
     * @param PageEntityInterface $page
     * @param list<CompiledTemplateInterface> $templates
     * @return void
     */
    private function load(PageEntityInterface $page, array $templates): void
    {
        try {
            $this->prefetch->forPage($page, $templates);
        } catch (\Throwable $e) {
            $this->logger->warning(
                'Rich snippets: the data of a page could not be loaded in one batch: ' . $e->getMessage(),
                ['exception' => $e, 'kind' => $page->getKind(), 'entity' => $page->getEntityId()]
            );
        }
    }

    /**
     * A snippet's block on a page; null when its value is empty or it failed
     *
     * @param SnippetInterface $snippet
     * @param PageEntityInterface $page
     * @param bool $pretty
     * @return RenderedBlockInterface|null
     */
    private function write(SnippetInterface $snippet, PageEntityInterface $page, bool $pretty): ?RenderedBlockInterface
    {
        try {
            $value = $this->engine->evaluate($snippet->getTemplate(), $this->scopes->create($page));
            if (!is_array($value) || $value === []) {
                return null;
            }

            return $this->blockFactory->create([
                'code' => $snippet->getCode(),
                'type' => $snippet->getType(),
                'json' => $this->json->forHtmlScript($value, $pretty),
            ]);
        } catch (\Throwable $e) {
            $this->logFailure($snippet, $e);

            return null;
        }
    }

    /**
     * Log why a snippet was left out, unless it was logged earlier in the request
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
            sprintf('Rich snippets: the snippet "%s" was left out of the page: %s', $code, $failure->getMessage()),
            ['exception' => $failure, 'snippet' => $code]
        );
    }
}
