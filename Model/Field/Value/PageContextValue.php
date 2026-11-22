<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Field\Value;

use Hryvinskyi\JsonTemplate\Api\Data\ValueInterface;
use Hryvinskyi\RichSnippets\Api\Data\PageEntityInterface;

/**
 * The page a scope evaluates for, under the root `context`: the roots of the page's values find their entity in it
 *
 * A template may read `context.kind`, `context.entity_id` and `context.store_id`.
 */
class PageContextValue implements ValueInterface
{
    public const ROOT = 'context';

    /**
     * @param PageEntityInterface $page
     */
    public function __construct(private readonly PageEntityInterface $page)
    {
    }

    /**
     * The page
     *
     * @return PageEntityInterface
     */
    public function getPage(): PageEntityInterface
    {
        return $this->page;
    }

    /**
     * @inheritDoc
     */
    public function has(string $key): bool
    {
        return in_array($key, ['kind', 'entity_id', 'store_id'], true);
    }

    /**
     * @inheritDoc
     */
    public function get(string $key): mixed
    {
        return match ($key) {
            'kind' => $this->page->getKind(),
            'entity_id' => $this->page->getEntityId(),
            'store_id' => $this->page->getStoreId(),
            default => null,
        };
    }
}
