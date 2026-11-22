<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Overview;

use Hryvinskyi\RichSnippets\Api\Data\FeatureOverviewInterface;
use Hryvinskyi\RichSnippets\Api\Data\FeatureTallyInterface;

/**
 * One kind of Google result on the Overview, with the kinds of page its snippets are output on
 */
class FeatureOverview implements FeatureOverviewInterface
{
    /**
     * @param FeatureTallyInterface $tally
     * @param list<string> $pageKinds
     */
    public function __construct(
        private readonly FeatureTallyInterface $tally,
        private readonly array $pageKinds
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getTally(): FeatureTallyInterface
    {
        return $this->tally;
    }

    /**
     * @inheritDoc
     */
    public function getPageKinds(): array
    {
        return $this->pageKinds;
    }
}
