<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Api;

use Hryvinskyi\RichSnippets\Api\Data\CheckRunInterface;

/**
 * Runs a claimed catalog check to its end
 *
 * Every store view the snippets are switched on in is checked as its storefront would be, one store view at a time;
 * the pages that exist there are taken kind by kind in batches, each batch reading its data in one go. Progress is
 * written after every batch, so a run that stops reporting it can be told from one still at work. The run ends done,
 * with its findings and counts complete, or failed.
 *
 * @api
 */
interface CatalogCheckRunnerInterface
{
    /**
     * Check every page for a run that was claimed, and end it done or failed
     *
     * @param CheckRunInterface $run a running run
     * @return void
     * @throws \Throwable whatever stopped the run, after it was marked failed
     */
    public function run(CheckRunInterface $run): void;
}
