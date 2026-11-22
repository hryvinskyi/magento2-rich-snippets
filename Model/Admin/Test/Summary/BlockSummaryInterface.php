<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Admin\Test\Summary;

/**
 * Says in one sentence what a JSON-LD object of one schema.org type describes, for a block row of the page test
 * ("A merchant listing with rating and a review.")
 *
 * One summary per type is listed in the container configuration; another module adds its own types there.
 */
interface BlockSummaryInterface
{
    /**
     * The sentence; empty when there is nothing to say
     *
     * @param array<mixed> $object
     * @return string
     */
    public function summarize(array $object): string;
}
