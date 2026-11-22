<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Test\Unit\Fake;

use Hryvinskyi\JsonTemplate\Api\Data\TraceOutcome;
use Hryvinskyi\RichSnippets\Api\Data\FeatureCheckInterface;
use Hryvinskyi\RichSnippets\Api\Data\FeatureTallyInterface;
use Hryvinskyi\RichSnippets\Api\Data\FeatureVerdict;
use Hryvinskyi\RichSnippets\Api\Data\LeftOutPropertyInterface;
use Hryvinskyi\RichSnippets\Api\Data\MissingPropertyInterface;
use Hryvinskyi\RichSnippets\Api\Data\OutputCheckInterface;
use Hryvinskyi\RichSnippets\Api\Data\PropertyTone;
use Hryvinskyi\RichSnippets\Api\Data\ResultTypeRuleInterface;
use Hryvinskyi\RichSnippets\Model\Requirement\Check\FeatureCheck;
use Hryvinskyi\RichSnippets\Model\Requirement\Check\FeatureTally;
use Hryvinskyi\RichSnippets\Model\Requirement\Check\LeftOutProperty;
use Hryvinskyi\RichSnippets\Model\Requirement\Check\MissingProperty;
use Hryvinskyi\RichSnippets\Model\Requirement\Check\OutputCheck;
use Hryvinskyi\RichSnippets\Model\Requirement\CheckFactoryInterface;

/**
 * Creates the real check results, as the generated factories do
 */
class CheckFactoryFake implements CheckFactoryInterface
{
    /**
     * @inheritDoc
     */
    public function missing(
        string $ruleCode,
        string $path,
        PropertyTone $tone,
        array $sources
    ): MissingPropertyInterface {
        return new MissingProperty($ruleCode, $path, $tone, $sources);
    }

    /**
     * @inheritDoc
     */
    public function leftOut(string $path, TraceOutcome $outcome, array $sources): LeftOutPropertyInterface
    {
        return new LeftOutProperty($path, $outcome, $sources);
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
        return new FeatureCheck($rule, $verdict, $missingRequired, $missingRecommended);
    }

    /**
     * @inheritDoc
     */
    public function output(string $type, array $features, array $leftOut): OutputCheckInterface
    {
        return new OutputCheck($type, $features, $leftOut);
    }

    /**
     * @inheritDoc
     */
    public function tally(ResultTypeRuleInterface $rule, int $met, int $notMet, int $leftOut): FeatureTallyInterface
    {
        return new FeatureTally($rule, $met, $notMet, $leftOut);
    }
}
