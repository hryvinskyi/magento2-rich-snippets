<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Engine;

use Hryvinskyi\JsonTemplate\Api\Data\CompiledTemplateInterface;
use Hryvinskyi\JsonTemplate\Api\Exception\CompileException;
use Hryvinskyi\JsonTemplate\Api\IncludeResolverInterface;
use Hryvinskyi\JsonTemplate\Api\TemplateEngineInterface;
use Hryvinskyi\RichSnippets\Model\ResourceModel\Snippet as SnippetResource;
use Magento\Framework\ObjectManager\ResetAfterRequestInterface;

/**
 * Finds the snippet an `@include` names by its code and gives its compiled template, through the engine's
 * compiled-template storage
 *
 * Any stored snippet can be included, switched on or not, with or without a page of its own: an included snippet is
 * a fragment of its includer. The stored templates are read in one query the first time a request includes one; a
 * template that no longer compiles is included as missing.
 */
class SnippetIncludeResolver implements IncludeResolverInterface, ResetAfterRequestInterface
{
    /**
     * @var array<string, string>|null templates by code
     */
    private ?array $templates = null;

    /**
     * @param SnippetResource $resource
     * @param TemplateEngineInterface $engine evaluates includes through this resolver, so it is given lazily
     */
    public function __construct(
        private readonly SnippetResource $resource,
        private readonly TemplateEngineInterface $engine
    ) {
    }

    /**
     * @inheritDoc
     */
    public function resolve(string $code): ?CompiledTemplateInterface
    {
        $this->templates ??= $this->resource->templatesByCode();
        $source = $this->templates[$code] ?? null;
        if ($source === null || $source === '') {
            return null;
        }
        try {
            return $this->engine->compile($source);
        } catch (CompileException) {
            return null;
        }
    }

    /**
     * @inheritDoc
     */
    public function _resetState(): void
    {
        $this->templates = null;
    }
}
