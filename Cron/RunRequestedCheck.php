<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Cron;

use Hryvinskyi\RichSnippets\Model\Check\RequestedChecks;

/**
 * Every minute: runs the requested catalog check, if there is one (see RequestedChecks)
 */
class RunRequestedCheck
{
    /**
     * @param RequestedChecks $checks
     */
    public function __construct(private readonly RequestedChecks $checks)
    {
    }

    /**
     * Run the job
     *
     * @return void
     * @throws \Throwable what made the check fail, so cron records the job as failed
     */
    public function execute(): void
    {
        $this->checks->runNext();
    }
}
