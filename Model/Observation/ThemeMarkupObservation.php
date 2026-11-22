<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Observation;

use Hryvinskyi\RichSnippets\Api\Data\MicrodataItemInterface;
use Hryvinskyi\RichSnippets\Api\Data\ThemeMarkupObservationInterface;

/**
 * What a page test saw of the theme's microdata on a page
 */
class ThemeMarkupObservation implements ThemeMarkupObservationInterface
{
    /**
     * @param int $storeId
     * @param string $kind
     * @param string $path
     * @param list<MicrodataItemInterface> $items
     * @param int $seenAt
     * @throws \InvalidArgumentException when the kind is empty or the path does not start with `/`
     */
    public function __construct(
        private readonly int $storeId,
        private readonly string $kind,
        private readonly string $path,
        private readonly array $items,
        private readonly int $seenAt
    ) {
        if ($kind === '' || !str_starts_with($path, '/')) {
            throw new \InvalidArgumentException('An observation names a kind of page and a path starting with "/".');
        }
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
    public function getKind(): string
    {
        return $this->kind;
    }

    /**
     * @inheritDoc
     */
    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * @inheritDoc
     */
    public function isFound(): bool
    {
        return $this->items !== [];
    }

    /**
     * @inheritDoc
     */
    public function getItems(): array
    {
        return $this->items;
    }

    /**
     * @inheritDoc
     */
    public function getSeenAt(): int
    {
        return $this->seenAt;
    }
}
