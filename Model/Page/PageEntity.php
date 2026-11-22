<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Page;

use Hryvinskyi\RichSnippets\Api\Data\PageEntityInterface;

/**
 * A storefront page: its kind, the entity it shows and its store view
 *
 * Refused when the kind is empty, the entity ID is not positive or the store ID is negative.
 */
class PageEntity implements PageEntityInterface
{
    /**
     * @param string $kind
     * @param int|null $entityId
     * @param int $storeId
     * @throws \InvalidArgumentException
     */
    public function __construct(
        private readonly string $kind,
        private readonly ?int $entityId,
        private readonly int $storeId
    ) {
        if ($kind === '') {
            throw new \InvalidArgumentException('A page needs a kind.');
        }
        if ($entityId !== null && $entityId < 1) {
            throw new \InvalidArgumentException('A page shows an entity with a positive ID, or none.');
        }
        if ($storeId < 0) {
            throw new \InvalidArgumentException('A page belongs to a store view with an ID of 0 or more.');
        }
    }

    /**
     * @inheritDoc
     */
    public function getKind(): string
    {
        return $this->kind;
    }

    /**
     * @inheritDoc
     */
    public function getEntityId(): ?int
    {
        return $this->entityId;
    }

    /**
     * @inheritDoc
     */
    public function getStoreId(): int
    {
        return $this->storeId;
    }
}
