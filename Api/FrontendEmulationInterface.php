<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Api;

/**
 * Runs work as the storefront of a store view would, for a guest, outside a storefront request
 *
 * The editor's preview, the page test's expected output and the catalog check evaluate snippets in the admin or in
 * cron; inside this the store view, its design, locale and configuration are the storefront's, the visitor is a
 * guest and prices are in the store view's default display currency, so URLs, images and prices are the ones a
 * visitor of that store view gets.
 *
 * @api
 */
interface FrontendEmulationInterface
{
    /**
     * Run a callable as the storefront of a store view
     *
     * A run inside a run for the same store view runs as part of it.
     *
     * @template T
     * @param int $storeId
     * @param callable():T $work
     * @return T what the callable returns
     * @throws \LogicException when called inside a run for another store view
     */
    public function run(int $storeId, callable $work): mixed;
}
