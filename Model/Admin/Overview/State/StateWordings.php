<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Admin\Overview\State;

use Hryvinskyi\RichSnippets\Api\Data\OverviewReportInterface;

/**
 * The Overview's state-dependent words for each setup state, by the state's value
 *
 * A state with no wording configured is an error in the configuration and fails loudly.
 */
class StateWordings
{
    /**
     * @param array<string,StateWordingInterface> $wordings by the setup state's value
     */
    public function __construct(private readonly array $wordings = [])
    {
    }

    /**
     * The words of the report's setup state
     *
     * @param OverviewReportInterface $report
     * @return StateWordingInterface
     * @throws \LogicException when no wording is configured for the state
     */
    public function of(OverviewReportInterface $report): StateWordingInterface
    {
        $state = $report->getSetupState()->value;
        $wording = $this->wordings[$state] ?? null;
        if (!$wording instanceof StateWordingInterface) {
            throw new \LogicException(sprintf('No Overview wording is configured for the state "%s".', $state));
        }

        return $wording;
    }
}
