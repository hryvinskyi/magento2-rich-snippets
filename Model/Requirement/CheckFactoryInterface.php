<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Requirement;

use Hryvinskyi\JsonTemplate\Api\Data\TraceOutcome;
use Hryvinskyi\RichSnippets\Api\Data\FeatureCheckInterface;
use Hryvinskyi\RichSnippets\Api\Data\FeatureTallyInterface;
use Hryvinskyi\RichSnippets\Api\Data\FeatureVerdict;
use Hryvinskyi\RichSnippets\Api\Data\LeftOutPropertyInterface;
use Hryvinskyi\RichSnippets\Api\Data\MissingPropertyInterface;
use Hryvinskyi\RichSnippets\Api\Data\OutputCheckInterface;
use Hryvinskyi\RichSnippets\Api\Data\PropertyTone;
use Hryvinskyi\RichSnippets\Api\Data\ResultTypeRuleInterface;

/**
 * Creates the results of a requirement check
 */
interface CheckFactoryInterface
{
    /**
     * A property a block misses
     *
     * @param string $ruleCode
     * @param string $path
     * @param PropertyTone $tone
     * @param list<string> $sources
     * @return MissingPropertyInterface
     */
    public function missing(
        string $ruleCode,
        string $path,
        PropertyTone $tone,
        array $sources
    ): MissingPropertyInterface;

    /**
     * A property left out of a block on a page
     *
     * @param string $path
     * @param TraceOutcome $outcome
     * @param list<string> $sources
     * @return LeftOutPropertyInterface
     */
    public function leftOut(string $path, TraceOutcome $outcome, array $sources): LeftOutPropertyInterface;

    /**
     * One block against one kind of result
     *
     * @param ResultTypeRuleInterface $rule
     * @param FeatureVerdict $verdict
     * @param list<MissingPropertyInterface> $missingRequired
     * @param list<MissingPropertyInterface> $missingRecommended
     * @return FeatureCheckInterface
     */
    public function feature(
        ResultTypeRuleInterface $rule,
        FeatureVerdict $verdict,
        array $missingRequired,
        array $missingRecommended
    ): FeatureCheckInterface;

    /**
     * One block against the rules of its types
     *
     * @param string $type
     * @param list<FeatureCheckInterface> $features
     * @param list<LeftOutPropertyInterface> $leftOut
     * @return OutputCheckInterface
     */
    public function output(string $type, array $features, array $leftOut): OutputCheckInterface;

    /**
     * The counts of one kind of result over many blocks
     *
     * @param ResultTypeRuleInterface $rule
     * @param int $met
     * @param int $notMet
     * @param int $leftOut
     * @return FeatureTallyInterface
     */
    public function tally(ResultTypeRuleInterface $rule, int $met, int $notMet, int $leftOut): FeatureTallyInterface;
}
