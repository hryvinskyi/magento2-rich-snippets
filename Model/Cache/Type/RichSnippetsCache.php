<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Cache\Type;

use Magento\Framework\App\Cache\Type\FrontendPool;
use Magento\Framework\Cache\Frontend\Decorator\TagScope;

/**
 * Cache type "Rich Snippets": what the module derives from the stored snippets, cleaned on its own from Cache
 * Management
 */
class RichSnippetsCache extends TagScope
{
    /**
     * Cache type code, unique among all cache types
     */
    public const TYPE_IDENTIFIER = 'rich_snippets';

    /**
     * Tag every entry of the type carries, which scopes cleaning to the type
     */
    public const CACHE_TAG = 'RICH_SNIPPETS';

    /**
     * @param FrontendPool $cacheFrontendPool
     */
    public function __construct(FrontendPool $cacheFrontendPool)
    {
        parent::__construct($cacheFrontendPool->get(self::TYPE_IDENTIFIER), self::CACHE_TAG);
    }
}
