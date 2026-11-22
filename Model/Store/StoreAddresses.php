<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Store;

use Magento\Framework\UrlInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;

/**
 * The storefront addresses of a store view: its pages, its media files and its static web files
 *
 * They are the store view's own, secure when its storefront is, whatever area or request the code runs in, so an
 * address built in the admin or in cron is the one a visitor of the store view gets.
 */
class StoreAddresses
{
    /**
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(private readonly StoreManagerInterface $storeManager)
    {
    }

    /**
     * The address a storefront path is reached at (`bags/field-satchel.html`); the home page for an empty path
     *
     * @param string $path
     * @param int $storeId
     * @return string
     */
    public function page(string $path, int $storeId): string
    {
        return $this->base(UrlInterface::URL_TYPE_LINK, $storeId) . ltrim($path, '/');
    }

    /**
     * The path of a page address of the store view, from its base address on, starting with `/`
     *
     * @param string $url an address the store view's base address starts (`https://shop.example/bags.html`)
     * @param int $storeId
     * @return string|null null when the address is not under the store view's base address
     */
    public function pathOf(string $url, int $storeId): ?string
    {
        $base = $this->base(UrlInterface::URL_TYPE_LINK, $storeId);

        return str_starts_with($url, $base) ? '/' . substr($url, strlen($base)) : null;
    }

    /**
     * The address of a file under the media directory (`catalog/product/f/s/fs-1.jpg`)
     *
     * @param string $path
     * @param int $storeId
     * @return string
     */
    public function media(string $path, int $storeId): string
    {
        return $this->base(UrlInterface::URL_TYPE_MEDIA, $storeId) . ltrim($path, '/');
    }

    /**
     * The address of a path under the web root (`media/catalog/category/bags.jpg`)
     *
     * @param string $path
     * @param int $storeId
     * @return string
     */
    public function web(string $path, int $storeId): string
    {
        return $this->base(UrlInterface::URL_TYPE_WEB, $storeId) . ltrim($path, '/');
    }

    /**
     * A base address of the store view
     *
     * @param string $type
     * @param int $storeId
     * @return string
     */
    private function base(string $type, int $storeId): string
    {
        $store = $this->storeManager->getStore($storeId);
        if (!$store instanceof Store) {
            throw new \LogicException(sprintf('Store view %d has no storefront addresses.', $storeId));
        }

        return rtrim((string) $store->getBaseUrl($type, (bool) $store->isFrontUrlSecure()), '/') . '/';
    }
}
