<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Api\Data;

/**
 * The admin screen that fixes a thing that wants fixing: a route path and its parameters, from which the admin
 * builds the address
 *
 * @api
 */
interface AttentionTargetInterface
{
    /**
     * The route path, `route id/controller/action`, never a front name (`hryvinskyi_richsnippets/test/index`)
     *
     * @return string
     */
    public function getRoute(): string;

    /**
     * The parameters the screen reads, by name (`["mode" => "catalog", "finding" => "merchant-listing-image"]`)
     *
     * @return array<string,string>
     */
    public function getParams(): array;
}
