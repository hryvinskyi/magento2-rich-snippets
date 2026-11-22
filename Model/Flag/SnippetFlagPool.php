<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Flag;

use Hryvinskyi\RichSnippets\Api\SnippetFlagProviderInterface;

/**
 * Every flag provider of the pool asked in turn; a snippet's flags are theirs in the pool's order
 */
class SnippetFlagPool implements SnippetFlagProviderInterface
{
    /**
     * @param array<string,SnippetFlagProviderInterface> $providers by name, in the order their flags are shown
     */
    public function __construct(private readonly array $providers = [])
    {
    }

    /**
     * @inheritDoc
     */
    public function flags(array $snippets, ?int $storeId): array
    {
        $flags = [];
        foreach ($this->providers as $provider) {
            foreach ($provider->flags($snippets, $storeId) as $code => $given) {
                $flags[(string) $code] = [...($flags[(string) $code] ?? []), ...$given];
            }
        }

        return $flags;
    }
}
