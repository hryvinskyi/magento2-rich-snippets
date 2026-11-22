<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Cron;

use Hryvinskyi\RichSnippets\Model\Check\Storage\CheckDataPruning;

/**
 * Once a day: drops old catalog checks, the pages of findings no screen shows any more and old idempotency keys
 * (see CheckDataPruning)
 */
class PruneCheckData
{
    /**
     * @param CheckDataPruning $pruning
     */
    public function __construct(private readonly CheckDataPruning $pruning)
    {
    }

    /**
     * Run the job
     *
     * @return void
     */
    public function execute(): void
    {
        $this->pruning->prune();
    }
}
