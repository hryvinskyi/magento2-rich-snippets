<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Requirement\Factory;

use Hryvinskyi\JsonTemplate\Api\Data\TraceOutcome;
use Hryvinskyi\RichSnippets\Api\Data\FeatureCheckInterface;
use Hryvinskyi\RichSnippets\Api\Data\FeatureCheckInterfaceFactory;
use Hryvinskyi\RichSnippets\Api\Data\FeatureTallyInterface;
use Hryvinskyi\RichSnippets\Api\Data\FeatureTallyInterfaceFactory;
use Hryvinskyi\RichSnippets\Api\Data\FeatureVerdict;
use Hryvinskyi\RichSnippets\Api\Data\LeftOutPropertyInterface;
use Hryvinskyi\RichSnippets\Api\Data\LeftOutPropertyInterfaceFactory;
use Hryvinskyi\RichSnippets\Api\Data\MissingPropertyInterface;
use Hryvinskyi\RichSnippets\Api\Data\MissingPropertyInterfaceFactory;
use Hryvinskyi\RichSnippets\Api\Data\OutputCheckInterface;
use Hryvinskyi\RichSnippets\Api\Data\OutputCheckInterfaceFactory;
use Hryvinskyi\RichSnippets\Api\Data\PropertyTone;
use Hryvinskyi\RichSnippets\Api\Data\ResultTypeRuleInterface;
use Hryvinskyi\RichSnippets\Model\Requirement\CheckFactoryInterface;

/**
 * Delegates to the generated factories of the check result contracts
 */
class CheckFactoryAdapter implements CheckFactoryInterface
{
    /**
     * @param MissingPropertyInterfaceFactory $missing
     * @param LeftOutPropertyInterfaceFactory $leftOut
     * @param FeatureCheckInterfaceFactory $features
     * @param OutputCheckInterfaceFactory $outputs
     * @param FeatureTallyInterfaceFactory $tallies
     */
    public function __construct(
        private readonly MissingPropertyInterfaceFactory $missing,
        private readonly LeftOutPropertyInterfaceFactory $leftOut,
        private readonly FeatureCheckInterfaceFactory $features,
        private readonly OutputCheckInterfaceFactory $outputs,
        private readonly FeatureTallyInterfaceFactory $tallies
    ) {
    }

    /**
     * @inheritDoc
     */
    public function missing(
        string $ruleCode,
        string $path,
        PropertyTone $tone,
        array $sources
    ): MissingPropertyInterface {
        return $this->missing->create([
            'ruleCode' => $ruleCode,
            'path' => $path,
            'tone' => $tone,
            'sources' => $sources,
        ]);
    }

    /**
     * @inheritDoc
     */
    public function leftOut(string $path, TraceOutcome $outcome, array $sources): LeftOutPropertyInterface
    {
        return $this->leftOut->create(['path' => $path, 'outcome' => $outcome, 'sources' => $sources]);
    }

    /**
     * @inheritDoc
     */
    public function feature(
        ResultTypeRuleInterface $rule,
        FeatureVerdict $verdict,
        array $missingRequired,
        array $missingRecommended
    ): FeatureCheckInterface {
        return $this->features->create([
            'rule' => $rule,
            'verdict' => $verdict,
            'missingRequired' => $missingRequired,
            'missingRecommended' => $missingRecommended,
        ]);
    }

    /**
     * @inheritDoc
     */
    public function output(string $type, array $features, array $leftOut): OutputCheckInterface
    {
        return $this->outputs->create(['type' => $type, 'features' => $features, 'leftOut' => $leftOut]);
    }

    /**
     * @inheritDoc
     */
    public function tally(ResultTypeRuleInterface $rule, int $met, int $notMet, int $leftOut): FeatureTallyInterface
    {
        return $this->tallies->create([
            'rule' => $rule,
            'met' => $met,
            'notMet' => $notMet,
            'leftOut' => $leftOut,
        ]);
    }
}
