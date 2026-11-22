<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Field\Value;

use Hryvinskyi\JsonTemplate\Api\Data\ValueInterface;
use Hryvinskyi\RichSnippets\Api\CollectionPoolInterface;
use Hryvinskyi\RichSnippets\Api\Data\PageEntityInterface;
use Hryvinskyi\RichSnippets\Api\FieldCatalogInterface;

/**
 * The values under a path of a root on a page (`product`, `store.address`), each resolved the first time a template
 * reads it and remembered for the rest of the evaluation
 *
 * A key is a field of the catalog, a list of the collection pool, or a step towards deeper fields (`address` under
 * `store`), which is again values of this kind.
 */
class FieldValues implements ValueInterface
{
    /**
     * @var array<string, mixed>
     */
    private array $resolved = [];

    /**
     * @param FieldCatalogInterface $catalog
     * @param CollectionPoolInterface $collections
     * @param FieldValuesFactory $branches
     * @param PageEntityInterface $page
     * @param int $subjectId the entity the root stands for on the page
     * @param string $prefix the path these values are under
     */
    public function __construct(
        private readonly FieldCatalogInterface $catalog,
        private readonly CollectionPoolInterface $collections,
        private readonly FieldValuesFactory $branches,
        private readonly PageEntityInterface $page,
        private readonly int $subjectId,
        private readonly string $prefix
    ) {
    }

    /**
     * @inheritDoc
     */
    public function has(string $key): bool
    {
        $path = $this->prefix . '.' . $key;

        return $this->catalog->getField($path) !== null
            || $this->collections->get($path) !== null
            || $this->catalog->isBranch($path);
    }

    /**
     * @inheritDoc
     */
    public function get(string $key): mixed
    {
        if (array_key_exists($key, $this->resolved)) {
            return $this->resolved[$key];
        }

        return $this->resolved[$key] = $this->resolve($this->prefix . '.' . $key);
    }

    /**
     * The value under a path
     *
     * @param string $path
     * @return mixed
     */
    private function resolve(string $path): mixed
    {
        $collection = $this->collections->get($path);
        if ($collection !== null) {
            return $collection->items($this->page, $this->subjectId);
        }
        if ($this->catalog->getField($path) !== null) {
            return $this->catalog->resolve($path, $this->subjectId, $this->page->getStoreId());
        }
        if ($this->catalog->isBranch($path)) {
            return $this->branches->create([
                'page' => $this->page,
                'subjectId' => $this->subjectId,
                'prefix' => $path,
            ]);
        }

        return null;
    }
}
