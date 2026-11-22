<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Requirement\Check;

use Hryvinskyi\RichSnippets\Api\Data\FeatureTallyInterface;
use Hryvinskyi\RichSnippets\Api\Data\GoogleStatus;
use Hryvinskyi\RichSnippets\Api\Data\ResultTypeRuleInterface;
use Hryvinskyi\RichSnippets\Api\Data\TallyStatus;

/**
 * The counts of one kind of Google result over many blocks, and the word they come to
 *
 * Blocks that leave an optional result out never make it partly met: a product with no reviews has nothing to fix.
 */
class FeatureTally implements FeatureTallyInterface
{
    /**
     * @param ResultTypeRuleInterface $rule
     * @param int $met
     * @param int $notMet
     * @param int $leftOut
     * @throws \InvalidArgumentException when a count is negative
     */
    public function __construct(
        private readonly ResultTypeRuleInterface $rule,
        private readonly int $met,
        private readonly int $notMet,
        private readonly int $leftOut
    ) {
        if ($met < 0 || $notMet < 0 || $leftOut < 0) {
            throw new \InvalidArgumentException('A tally counts blocks; a count cannot be negative.');
        }
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
    public function getMet(): int
    {
        return $this->met;
    }

    /**
     * @inheritDoc
     */
    public function getNotMet(): int
    {
        return $this->notMet;
    }

    /**
     * @inheritDoc
     */
    public function getLeftOut(): int
    {
        return $this->leftOut;
    }

    /**
     * @inheritDoc
     */
    public function getTotal(): int
    {
        return $this->met + $this->notMet + $this->leftOut;
    }

    /**
     * @inheritDoc
     */
    public function getStatus(): TallyStatus
    {
        if ($this->rule->getGoogleStatus() !== GoogleStatus::Ok) {
            return TallyStatus::NoResult;
        }

        return $this->notMet > 0 ? TallyStatus::Partly : TallyStatus::Met;
    }
}
