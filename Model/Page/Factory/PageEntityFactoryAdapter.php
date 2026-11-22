<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Page\Factory;

use Hryvinskyi\RichSnippets\Api\Data\PageEntityInterface;
use Hryvinskyi\RichSnippets\Api\Data\PageEntityInterfaceFactory;
use Hryvinskyi\RichSnippets\Api\PageEntityFactoryInterface;

/**
 * Delegates to the generated factory of the page contract
 */
class PageEntityFactoryAdapter implements PageEntityFactoryInterface
{
    /**
     * @param PageEntityInterfaceFactory $generated
     */
    public function __construct(private readonly PageEntityInterfaceFactory $generated)
    {
    }

    /**
     * @inheritDoc
     */
    public function create(string $kind, ?int $entityId, int $storeId): PageEntityInterface
    {
        return $this->generated->create(['kind' => $kind, 'entityId' => $entityId, 'storeId' => $storeId]);
    }
}
