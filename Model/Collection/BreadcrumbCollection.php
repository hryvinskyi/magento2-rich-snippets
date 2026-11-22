<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Collection;

use Hryvinskyi\RichSnippets\Api\CollectionInterface;
use Hryvinskyi\RichSnippets\Api\Data\PageEntityInterface;
use Hryvinskyi\RichSnippets\Model\Breadcrumb\TrailInterface;
use Hryvinskyi\RichSnippets\Model\Store\StoreAddresses;

/**
 * The breadcrumb levels of a page: the home page, then the levels of the page's kind, each with its `position`
 * (from 1), `name` and `item` (absolute address)
 *
 * A kind of page without a trail of its own (the home page, every other page) has no levels.
 */
class BreadcrumbCollection implements CollectionInterface
{
    /**
     * @param StoreAddresses $addresses
     * @param string $path
     * @param string $itemName
     * @param string $label
     * @param array<string,TrailInterface> $trails by kind of page
     */
    public function __construct(
        private readonly StoreAddresses $addresses,
        private readonly string $path,
        private readonly string $itemName,
        private readonly string $label,
        private readonly array $trails = []
    ) {
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
    public function getItemName(): string
    {
        return $this->itemName;
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
    public function items(PageEntityInterface $page, int $subjectId): array
    {
        $trail = $this->trails[$page->getKind()] ?? null;
        $levels = $trail?->trail($page, $subjectId) ?? [];
        if ($levels === []) {
            return [];
        }
        $home = ['name' => (string) __('Home'), 'item' => $this->addresses->page('', $page->getStoreId())];
        $items = [];
        foreach ([$home, ...$levels] as $index => $level) {
            $items[] = ['position' => $index + 1, 'name' => $level['name'], 'item' => $level['item']];
        }

        return $items;
    }
}
