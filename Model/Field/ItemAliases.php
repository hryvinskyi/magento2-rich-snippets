<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Field;

use Hryvinskyi\JsonTemplate\Api\ItemAliasResolverInterface;
use Hryvinskyi\RichSnippets\Api\CollectionPoolInterface;

/**
 * The name each list's items are read by, as the list declares it (`product.gallery` → `image`)
 */
class ItemAliases implements ItemAliasResolverInterface
{
    /**
     * @param CollectionPoolInterface $collections
     */
    public function __construct(private readonly CollectionPoolInterface $collections)
    {
    }

    /**
     * @inheritDoc
     */
    public function resolve(string $collection): ?string
    {
        return $this->collections->get($collection)?->getItemName();
    }
}
