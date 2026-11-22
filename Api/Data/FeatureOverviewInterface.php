<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Api\Data;

/**
 * One kind of Google result on the Overview: how many of the last check's blocks have what it needs, and where the
 * snippets that write it are output
 *
 * @api
 */
interface FeatureOverviewInterface
{
    /**
     * The counts of the last check's blocks against the result's requirements, in the scope
     *
     * @return FeatureTallyInterface
     */
    public function getTally(): FeatureTallyInterface;

    /**
     * The kinds of page the switched-on snippets of the result's types are set for, in the kinds' order
     *
     * @return list<string>
     */
    public function getPageKinds(): array;
}
