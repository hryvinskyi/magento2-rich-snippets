<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Snippet\Storage;

use Hryvinskyi\JsonTemplate\Api\Exception\CompileException;
use Hryvinskyi\JsonTemplate\Api\TemplateEngineInterface;
use Hryvinskyi\RichSnippets\Model\ResourceModel\Snippet as SnippetResource;
use Hryvinskyi\RichSnippets\Model\Snippet\IncludeEdgesInterface;

/**
 * The include graph of the snippet table, read from each template's compiled form
 *
 * The engine lists the codes a compiled template includes; nothing here reads the template's text for them. The
 * engine keeps compiled forms, so a template is compiled once however often the graph is read.
 */
class StoredIncludeEdges implements IncludeEdgesInterface
{
    /**
     * @param SnippetResource $resource
     * @param TemplateEngineInterface $engine
     */
    public function __construct(
        private readonly SnippetResource $resource,
        private readonly TemplateEngineInterface $engine
    ) {
    }

    /**
     * @inheritDoc
     */
    public function edges(): array
    {
        $edges = [];
        foreach ($this->resource->templatesByCode() as $code => $template) {
            try {
                $edges[$code] = $this->engine->compile($template)->getIncludes();
            } catch (CompileException) {
                $edges[$code] = [];
            }
        }

        return $edges;
    }
}
