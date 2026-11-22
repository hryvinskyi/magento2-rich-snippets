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
use Hryvinskyi\RichSnippets\Api\EntityDataSourceInterface;

/**
 * A list whose items a part of an entity source holds (a product's gallery, its reviews, its variants)
 */
class PartCollection implements CollectionInterface
{
    /**
     * @param EntityDataSourceInterface $source
     * @param string $part
     * @param string $path
     * @param string $itemName
     * @param string $label
     */
    public function __construct(
        private readonly EntityDataSourceInterface $source,
        private readonly string $part,
        private readonly string $path,
        private readonly string $itemName,
        private readonly string $label
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
        $items = $this->source->get($subjectId, $page->getStoreId(), $this->part);
        $list = [];
        foreach (is_array($items) ? $items : [] as $item) {
            if (is_array($item)) {
                $values = [];
                foreach ($item as $name => $value) {
                    $values[(string) $name] = $value;
                }
                $list[] = $values;
            }
        }

        return $list;
    }
}
