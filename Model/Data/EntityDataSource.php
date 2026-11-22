<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Data;

use Hryvinskyi\RichSnippets\Api\EntityDataPartInterface;
use Hryvinskyi\RichSnippets\Api\EntityDataSourceInterface;

/**
 * An entity source made of parts: each part loads its share of the data for every named entity of a store view
 * the first time one of them is read, and the source keeps what it loaded until it is reset
 *
 * The variable paths of every call add up; the collection limits of the latest call replace the earlier ones. A
 * call that adds a path the store view's parts were not loaded with drops what they loaded, so the next read loads
 * them again with every path.
 */
class EntityDataSource implements EntityDataSourceInterface
{
    /**
     * @var array<int, array<int, true>> the named entities, by store view
     */
    private array $named = [];

    /**
     * @var array<int, array<string, true>> the variable paths, by store view
     */
    private array $paths = [];

    /**
     * @var array<int, array<string, int>> the collection limits, by store view
     */
    private array $limits = [];

    /**
     * @var array<int, array<string, array<int, mixed>>> what each part loaded, by store view, part and entity
     */
    private array $loaded = [];

    /**
     * @param array<string,EntityDataPartInterface> $parts by code
     */
    public function __construct(private readonly array $parts = [])
    {
    }

    /**
     * @inheritDoc
     */
    public function prefetch(array $ids, int $storeId, array $variablePaths, array $collectionLimits = []): void
    {
        foreach ($ids as $id) {
            $this->named[$storeId][$id] = true;
        }
        foreach ($variablePaths as $path) {
            if (!isset($this->paths[$storeId][$path])) {
                $this->paths[$storeId][$path] = true;
                unset($this->loaded[$storeId]);
            }
        }
        $this->limits[$storeId] = $collectionLimits;
    }

    /**
     * @inheritDoc
     */
    public function get(int $entityId, int $storeId, string $part): mixed
    {
        $loader = $this->parts[$part] ?? null;
        if ($loader === null) {
            throw new \OutOfBoundsException(sprintf('The entity source has no part "%s".', $part));
        }
        $loaded = $this->loaded[$storeId][$part] ?? [];
        if (!array_key_exists($entityId, $loaded)) {
            $pending = [$entityId => true];
            foreach (array_keys($this->named[$storeId] ?? []) as $id) {
                if (!array_key_exists($id, $loaded)) {
                    $pending[$id] = true;
                }
            }
            $ids = array_keys($pending);
            $values = $loader->load(
                $ids,
                $storeId,
                array_keys($this->paths[$storeId] ?? []),
                $this->limits[$storeId] ?? []
            );
            foreach ($ids as $id) {
                $loaded[$id] = $values[$id] ?? null;
            }
            $this->loaded[$storeId][$part] = $loaded;
        }

        return $loaded[$entityId];
    }

    /**
     * @inheritDoc
     */
    public function reset(): void
    {
        $this->named = [];
        $this->paths = [];
        $this->limits = [];
        $this->loaded = [];
    }
}
