<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Api;

use Hryvinskyi\RichSnippets\Api\Data\AttentionItemInterface;
use Hryvinskyi\RichSnippets\Api\Data\AttentionScopeInterface;

/**
 * Finds one sort of thing that wants fixing for the Overview's "Needs attention" list
 *
 * Providers are a pool another module adds to; the list shows their items in the pool's order.
 *
 * @api
 */
interface AttentionItemProviderInterface
{
    /**
     * The items of this sort that apply in the scope; none when nothing wants fixing
     *
     * @param AttentionScopeInterface $scope
     * @return list<AttentionItemInterface>
     */
    public function items(AttentionScopeInterface $scope): array;
}
