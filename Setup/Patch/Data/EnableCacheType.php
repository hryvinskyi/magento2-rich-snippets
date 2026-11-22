<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Setup\Patch\Data;

use Hryvinskyi\RichSnippets\Model\Cache\Type\RichSnippetsCache;
use Magento\Framework\App\Cache\Manager;
use Magento\Framework\Setup\Patch\DataPatchInterface;

/**
 * Turns the module's cache type on when the module is added to, or upgraded on, an existing store
 *
 * A fresh installation enables every cache type, but adding a module to an installed store leaves its new type
 * disabled, and a disabled type caches nothing. Magento records an applied patch, so this runs once; a merchant who
 * disables the type afterwards keeps that choice.
 */
class EnableCacheType implements DataPatchInterface
{
    /**
     * @param Manager $cacheManager
     */
    public function __construct(private readonly Manager $cacheManager)
    {
    }

    /**
     * Enable the module's cache type
     *
     * @return $this
     */
    public function apply(): self
    {
        $this->cacheManager->setEnabled([RichSnippetsCache::TYPE_IDENTIFIER], true);

        return $this;
    }

    /**
     * @inheritDoc
     */
    public static function getDependencies(): array
    {
        return [];
    }

    /**
     * @inheritDoc
     */
    public function getAliases(): array
    {
        return [];
    }
}
