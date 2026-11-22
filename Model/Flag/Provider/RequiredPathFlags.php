<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Flag\Provider;

use Hryvinskyi\JsonTemplate\Api\Exception\CompileException;
use Hryvinskyi\JsonTemplate\Api\TemplateEngineInterface;
use Hryvinskyi\RichSnippets\Api\Data\FlagTone;
use Hryvinskyi\RichSnippets\Api\Data\MissingPropertyInterface;
use Hryvinskyi\RichSnippets\Api\Data\PropertyTone;
use Hryvinskyi\RichSnippets\Api\RequirementCheckerInterface;
use Hryvinskyi\RichSnippets\Api\ResultTypeRulePoolInterface;
use Hryvinskyi\RichSnippets\Api\SnippetFlagFactoryInterface;
use Hryvinskyi\RichSnippets\Api\SnippetFlagProviderInterface;

/**
 * Flags, as bad, each required property a snippet's template can never write, whatever the page
 *
 * Read from the template alone, so it holds in every store view. Only a property Google needs for a result it shows
 * is flagged; one an optional result needs is not. A stored template that no longer compiles gets no flag here. A
 * flag reads as the title the rule gives the missing requirement ("No offer: Google needs one"); a requirement with no
 * title is named by its path and the result it belongs to.
 */
class RequiredPathFlags implements SnippetFlagProviderInterface
{
    /**
     * @param TemplateEngineInterface $engine
     * @param RequirementCheckerInterface $checker
     * @param ResultTypeRulePoolInterface $rules
     * @param SnippetFlagFactoryInterface $flags
     */
    public function __construct(
        private readonly TemplateEngineInterface $engine,
        private readonly RequirementCheckerInterface $checker,
        private readonly ResultTypeRulePoolInterface $rules,
        private readonly SnippetFlagFactoryInterface $flags
    ) {
    }

    /**
     * @inheritDoc
     */
    public function flags(array $snippets, ?int $storeId): array
    {
        $flags = [];
        foreach ($snippets as $snippet) {
            try {
                $compiled = $this->engine->compile($snippet->getTemplate());
            } catch (CompileException) {
                continue;
            }
            $seen = [];
            foreach ($this->checker->checkTemplate($compiled) as $missing) {
                $path = $missing->getPath();
                if ($missing->getTone() !== PropertyTone::Bad || isset($seen[$path])) {
                    continue;
                }
                $seen[$path] = true;
                $flags[$snippet->getCode()][] = $this->flags->create(FlagTone::Bad, $this->title($missing));
            }
        }

        return $flags;
    }

    /**
     * What a flag says about a missing property: the title its requirement gives, else the path and the result
     *
     * @param MissingPropertyInterface $missing
     * @return string
     */
    private function title(MissingPropertyInterface $missing): string
    {
        $rule = $this->rules->get($missing->getRuleCode());
        foreach ($rule?->getRequired() ?? [] as $requirement) {
            $title = $requirement->getMissingTitle();
            if ($title !== '' && in_array($missing->getPath(), $requirement->getPaths(), true)) {
                return (string) __($title);
            }
        }

        return (string) __(
            '%1 is missing: Google needs it for %2',
            $missing->getPath(),
            __($rule?->getPhrase() ?? $missing->getRuleCode())
        );
    }
}
