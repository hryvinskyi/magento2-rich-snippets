<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Flag\Provider;

use Hryvinskyi\RichSnippets\Api\Data\FlagTone;
use Hryvinskyi\RichSnippets\Api\Data\GoogleStatus;
use Hryvinskyi\RichSnippets\Api\ResultTypeRulePoolInterface;
use Hryvinskyi\RichSnippets\Api\SnippetFlagFactoryInterface;
use Hryvinskyi\RichSnippets\Api\SnippetFlagProviderInterface;
use Hryvinskyi\RichSnippets\Model\Snippet\TemplateTypeInterface;

/**
 * Notes, for a snippet whose types can have a kind of result Google has stopped showing, that it no longer gives one
 *
 * Every type the template's root names is looked at, and each such result is noted once.
 */
class GoogleStatusFlags implements SnippetFlagProviderInterface
{
    /**
     * @param ResultTypeRulePoolInterface $rules
     * @param TemplateTypeInterface $types
     * @param SnippetFlagFactoryInterface $flags
     */
    public function __construct(
        private readonly ResultTypeRulePoolInterface $rules,
        private readonly TemplateTypeInterface $types,
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
            $noted = [];
            foreach ($this->types->typesOf($snippet->getTemplate()) as $type) {
                foreach ($this->rules->forType($type) as $rule) {
                    if ($rule->getGoogleStatus() !== GoogleStatus::Gone || isset($noted[$rule->getCode()])) {
                        continue;
                    }
                    $noted[$rule->getCode()] = true;
                    $flags[$snippet->getCode()][] = $this->flags->create(
                        FlagTone::Note,
                        (string) __('Google no longer shows %1', __($rule->getPhrase()))
                    );
                }
            }
        }

        return $flags;
    }
}
