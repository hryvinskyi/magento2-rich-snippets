<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Overview;

use Hryvinskyi\RichSnippets\Api\AttentionItemProviderInterface;
use Hryvinskyi\RichSnippets\Api\Data\AttentionItemInterface;
use Hryvinskyi\RichSnippets\Api\Data\AttentionScopeInterface;

/**
 * Everything that wants fixing, asked of each provider in the pool's order
 *
 * A pool member that is not a provider is an error in the configuration and fails loudly.
 */
class AttentionItems
{
    /**
     * @param array<string,mixed> $providers by name, in the order their items are listed
     */
    public function __construct(private readonly array $providers = [])
    {
    }

    /**
     * The items of every provider
     *
     * @param AttentionScopeInterface $scope
     * @return list<AttentionItemInterface>
     * @throws \LogicException when a pool member is not a provider
     */
    public function items(AttentionScopeInterface $scope): array
    {
        $items = [];
        foreach ($this->providers as $name => $provider) {
            if (!$provider instanceof AttentionItemProviderInterface) {
                throw new \LogicException(sprintf('The attention item provider "%s" is not one.', $name));
            }
            array_push($items, ...$provider->items($scope));
        }

        return $items;
    }
}
