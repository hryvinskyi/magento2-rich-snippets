<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Api;

use Hryvinskyi\RichSnippets\Api\Data\ThemeMarkupObservationInterface;

/**
 * Keeps, per store view and kind of page, what the last page test saw of the theme's own microdata
 *
 * One observation per store view and kind: a new one replaces the one before, so a page test that finds nothing
 * after the markup was removed says so.
 *
 * @api
 */
interface ThemeMarkupObservationsInterface
{
    /**
     * Keep an observation in place of the one before for its store view and kind
     *
     * @param ThemeMarkupObservationInterface $observation
     * @return void
     */
    public function record(ThemeMarkupObservationInterface $observation): void;

    /**
     * The last observation of a kind of page of a store view
     *
     * @param int $storeId
     * @param string $kind
     * @return ThemeMarkupObservationInterface|null null when no page test has read such a page yet
     */
    public function latest(int $storeId, string $kind): ?ThemeMarkupObservationInterface;
}
