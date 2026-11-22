<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Check;

use Hryvinskyi\RichSnippets\Api\Data\FindingItemInterface;

/**
 * One page behind a finding, with the names the admin knows it by
 */
class FindingItem implements FindingItemInterface
{
    /**
     * @param string $kind
     * @param int|null $entityId
     * @param int $storeId
     * @param string $label
     * @param string|null $sku
     * @param string|null $group
     */
    public function __construct(
        private readonly string $kind,
        private readonly ?int $entityId,
        private readonly int $storeId,
        private readonly string $label,
        private readonly ?string $sku = null,
        private readonly ?string $group = null
    ) {
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

    /**
     * @inheritDoc
     */
    public function getLabel(): string
    {
        return $this->label;
    }

    /**
     * @inheritDoc
     */
    public function getSku(): ?string
    {
        return $this->sku;
    }

    /**
     * @inheritDoc
     */
    public function getGroup(): ?string
    {
        return $this->group;
    }
}
