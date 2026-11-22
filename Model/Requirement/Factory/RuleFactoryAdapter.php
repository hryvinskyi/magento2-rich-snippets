<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Requirement\Factory;

use Hryvinskyi\RichSnippets\Api\Data\GoogleStatus;
use Hryvinskyi\RichSnippets\Api\Data\RequirementInterface;
use Hryvinskyi\RichSnippets\Api\Data\RequirementInterfaceFactory;
use Hryvinskyi\RichSnippets\Api\Data\ResultTypeRuleInterface;
use Hryvinskyi\RichSnippets\Api\Data\ResultTypeRuleInterfaceFactory;
use Hryvinskyi\RichSnippets\Model\Requirement\RuleFactoryInterface;

/**
 * Delegates to the generated factories of the rule contracts
 */
class RuleFactoryAdapter implements RuleFactoryInterface
{
    /**
     * @param RequirementInterfaceFactory $requirements
     * @param ResultTypeRuleInterfaceFactory $rules
     */
    public function __construct(
        private readonly RequirementInterfaceFactory $requirements,
        private readonly ResultTypeRuleInterfaceFactory $rules
    ) {
    }

    /**
     * @inheritDoc
     */
    public function requirement(array $paths, string $missingTitle = '', string $why = ''): RequirementInterface
    {
        return $this->requirements->create(['paths' => $paths, 'missingTitle' => $missingTitle, 'why' => $why]);
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
        return $this->rules->create([
            'code' => $code,
            'label' => $label,
            'phrase' => $phrase,
            'anchors' => $anchors,
            'optional' => $optional,
            'googleStatus' => $googleStatus,
            'required' => $required,
            'recommended' => $recommended,
            'requirementText' => $requirementText,
            'metText' => $metText,
            'leftOutText' => $leftOutText,
            'statusText' => $statusText,
        ]);
    }
}
