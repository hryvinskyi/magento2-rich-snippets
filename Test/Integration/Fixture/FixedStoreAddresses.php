<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Test\Integration\Fixture;

use Hryvinskyi\RichSnippets\Model\Store\StoreAddresses;

/**
 * Store view addresses with a base address a test chooses, for every store view
 *
 * The sandbox keeps no base address of its own, so a test that needs a known host gives one here.
 */
class FixedStoreAddresses extends StoreAddresses
{
    /**
     * @var string
     */
    private string $base = 'http://shop.test/';

    /**
     * Use another base address
     *
     * @param string $base ending with `/`
     * @return void
     */
    public function useBase(string $base): void
    {
        $this->base = $base;
    }

    /**
     * @inheritDoc
     */
    public function page(string $path, int $storeId): string
    {
        return $this->base . ltrim($path, '/');
    }

    /**
     * @inheritDoc
     */
    public function pathOf(string $url, int $storeId): ?string
    {
        return str_starts_with($url, $this->base) ? '/' . substr($url, strlen($this->base)) : null;
    }
}
