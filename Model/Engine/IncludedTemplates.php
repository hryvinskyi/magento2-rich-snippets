<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Engine;

use Hryvinskyi\JsonTemplate\Api\Data\CompiledTemplateInterface;
use Hryvinskyi\JsonTemplate\Api\IncludeResolverInterface;

/**
 * Every template an evaluation of some templates can reach: the templates themselves and the ones they include,
 * directly or through others
 *
 * What they read is known before they are evaluated, so the data of all of them is loaded at once. Each code is
 * resolved once, so an include loop ends; a code that resolves to nothing adds nothing.
 */
class IncludedTemplates
{
    /**
     * @param IncludeResolverInterface $includes finds an included template by its code
     */
    public function __construct(private readonly IncludeResolverInterface $includes)
    {
    }

    /**
     * The templates and every template they reach, each once, the given ones first
     *
     * @param list<CompiledTemplateInterface> $templates
     * @return list<CompiledTemplateInterface>
     */
    public function withIncludes(array $templates): array
    {
        $reached = [];
        $pending = [];
        foreach ($templates as $template) {
            $reached[$template->getSourceHash()] = $template;
            array_push($pending, ...$template->getIncludes());
        }

        $resolved = [];
        while ($pending !== []) {
            $code = array_shift($pending);
            if (isset($resolved[$code])) {
                continue;
            }
            $resolved[$code] = true;
            $template = $this->includes->resolve($code);
            if ($template === null) {
                continue;
            }
            $reached[$template->getSourceHash()] ??= $template;
            array_push($pending, ...$template->getIncludes());
        }

        return array_values($reached);
    }
}
