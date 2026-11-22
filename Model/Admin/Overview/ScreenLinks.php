<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Admin\Overview;

/**
 * Writes a link to an admin screen as the Overview's page reads it: `{"route", "params"}`, from which the page builds
 * the address
 *
 * The parameters are always a JSON object, `{}` when there are none, so the page never has to tell an empty list from
 * an empty set of parameters. A store view, when given, is added as `store`.
 *
 * @phpstan-type Target array{route: string, params: array<string, string>|\stdClass}
 */
class ScreenLinks
{
    /**
     * A link to a screen
     *
     * @param string $route `route id/controller/action`
     * @param array<string,string> $params
     * @param int|null $storeId the store view to open the screen in; none when null
     * @return Target
     */
    public function target(string $route, array $params = [], ?int $storeId = null): array
    {
        if ($storeId !== null) {
            $params['store'] = (string) $storeId;
        }

        return ['route' => $route, 'params' => $params === [] ? (object) [] : $params];
    }
}
