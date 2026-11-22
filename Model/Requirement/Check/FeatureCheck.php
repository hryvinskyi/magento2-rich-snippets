<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Requirement\Check;

use Hryvinskyi\RichSnippets\Api\Data\FeatureCheckInterface;
use Hryvinskyi\RichSnippets\Api\Data\FeatureVerdict;
use Hryvinskyi\RichSnippets\Api\Data\MissingPropertyInterface;
use Hryvinskyi\RichSnippets\Api\Data\ResultTypeRuleInterface;

/**
 * One block against one kind of Google result
 */
class FeatureCheck implements FeatureCheckInterface
{
    /**
     * @param ResultTypeRuleInterface $rule
     * @param FeatureVerdict $verdict
     * @param list<MissingPropertyInterface> $missingRequired
     * @param list<MissingPropertyInterface> $missingRecommended
     */
    public function __construct(
        private readonly ResultTypeRuleInterface $rule,
        private readonly FeatureVerdict $verdict,
        private readonly array $missingRequired = [],
        private readonly array $missingRecommended = []
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getRule(): ResultTypeRuleInterface
    {
        return $this->rule;
    }

    /**
     * @inheritDoc
     */
    public function getVerdict(): FeatureVerdict
    {
        return $this->verdict;
    }

    /**
     * @inheritDoc
     */
    public function getMissingRequired(): array
    {
        return $this->missingRequired;
    }

    /**
     * @inheritDoc
     */
    public function getMissingRecommended(): array
    {
        return $this->missingRecommended;
    }
}
