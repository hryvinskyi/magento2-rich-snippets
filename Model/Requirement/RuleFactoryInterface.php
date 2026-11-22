<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Requirement;

use Hryvinskyi\RichSnippets\Api\Data\GoogleStatus;
use Hryvinskyi\RichSnippets\Api\Data\RequirementInterface;
use Hryvinskyi\RichSnippets\Api\Data\ResultTypeRuleInterface;
use Hryvinskyi\RichSnippets\Api\Exception\MalformedRuleException;

/**
 * Creates the values a requirement rule is made of
 */
interface RuleFactoryInterface
{
    /**
     * A requirement of one path, or of several any one of which will do
     *
     * @param list<string> $paths
     * @param string $missingTitle the title shown when the requirement is missing; empty for none
     * @param string $why why the property is worth adding; empty for none
     * @return RequirementInterface
     * @throws MalformedRuleException when the paths or the phrases break a requirement's shape
     */
    public function requirement(array $paths, string $missingTitle = '', string $why = ''): RequirementInterface;

    /**
     * A rule
     *
     * @param string $code
     * @param string $label
     * @param string $phrase
     * @param array<string,string> $anchors anchor by schema.org type
     * @param bool $optional
     * @param GoogleStatus $googleStatus
     * @param list<RequirementInterface> $required
     * @param list<RequirementInterface> $recommended
     * @param string $requirementText
     * @param string $metText
     * @param string $leftOutText
     * @param string $statusText
     * @return ResultTypeRuleInterface
     * @throws MalformedRuleException when a value breaks the rule's shape
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
    ): ResultTypeRuleInterface;
}
