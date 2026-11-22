<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Test\Unit\Fake;

use Hryvinskyi\RichSnippets\Api\Data\GoogleStatus;
use Hryvinskyi\RichSnippets\Api\Data\RequirementInterface;
use Hryvinskyi\RichSnippets\Api\Data\ResultTypeRuleInterface;
use Hryvinskyi\RichSnippets\Model\Requirement\Requirement;
use Hryvinskyi\RichSnippets\Model\Requirement\ResultTypeRule;
use Hryvinskyi\RichSnippets\Model\Requirement\RuleFactoryInterface;

/**
 * Creates the real rule values, as the generated factories do
 */
class RuleFactoryFake implements RuleFactoryInterface
{
    /**
     * @inheritDoc
     */
    public function requirement(array $paths, string $missingTitle = '', string $why = ''): RequirementInterface
    {
        return new Requirement($paths, $missingTitle, $why);
    }

    /**
     * @inheritDoc
     */
    public function rule(
        string $code,
        string $label,
        string $phrase,
        array $anchors,
        bool $optional,
        GoogleStatus $googleStatus,
        array $required,
        array $recommended,
        string $requirementText,
        string $metText,
        string $leftOutText,
        string $statusText
    ): ResultTypeRuleInterface {
        return new ResultTypeRule(
            $code,
            $label,
            $phrase,
            $anchors,
            $optional,
            $googleStatus,
            $required,
            $recommended,
            $requirementText,
            $metText,
            $leftOutText,
            $statusText
        );
    }
}
