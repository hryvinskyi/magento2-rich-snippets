<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Check;

use Hryvinskyi\RichSnippets\Api\CatalogCheckInterface;
use Hryvinskyi\RichSnippets\Api\Data\CheckRunInterface;
use Hryvinskyi\RichSnippets\Api\Data\CheckTrigger;
use Hryvinskyi\RichSnippets\Model\Config\CheckSettings;

/**
 * Asks for a check by itself, at night or after a product import, unless the check is switched off
 *
 * It only asks: the minute job runs the check, as it runs every other. Switched off, nothing is asked, whatever
 * schedule the cron configuration still holds; a check asked for by hand still runs.
 */
class AutomaticCheck
{
    /**
     * @param CheckSettings $settings
     * @param CatalogCheckInterface $catalogCheck
     */
    public function __construct(
        private readonly CheckSettings $settings,
        private readonly CatalogCheckInterface $catalogCheck
    ) {
    }

    /**
     * Ask for a check when the check runs by itself
     *
     * @param CheckTrigger $trigger
     * @return CheckRunInterface|null the requested or running run; null when the check is switched off
     */
    public function request(CheckTrigger $trigger): ?CheckRunInterface
    {
        if (!$this->settings->isEnabled()) {
            return null;
        }

        return $this->catalogCheck->request($trigger);
    }
}
