<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Cron;

use Hryvinskyi\RichSnippets\Api\Data\CheckTrigger;
use Hryvinskyi\RichSnippets\Model\Check\AutomaticCheck;

/**
 * At the time set in the settings: asks for the nightly catalog check, unless the check is switched off
 *
 * It only asks; the minute job runs it.
 */
class ScheduleCheck
{
    /**
     * @param AutomaticCheck $automaticCheck
     */
    public function __construct(private readonly AutomaticCheck $automaticCheck)
    {
    }

    /**
     * Run the job
     *
     * @return void
     */
    public function execute(): void
    {
        $this->automaticCheck->request(CheckTrigger::Schedule);
    }
}
