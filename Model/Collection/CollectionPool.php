<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Collection;

use Hryvinskyi\RichSnippets\Api\CollectionInterface;
use Hryvinskyi\RichSnippets\Api\CollectionPoolInterface;

/**
 * The registered lists, by the path each declares
 */
class CollectionPool implements CollectionPoolInterface
{
    /**
     * @var array<string, CollectionInterface>|null
     */
    private ?array $byPath = null;

    /**
     * @param array<string,CollectionInterface> $collections
     */
    public function __construct(private readonly array $collections = [])
    {
    }

    /**
     * @inheritDoc
     */
    public function get(string $path): ?CollectionInterface
    {
        return $this->byPath()[$path] ?? null;
    }

    /**
     * @inheritDoc
     */
    public function getCollections(): array
    {
        return array_values($this->byPath());
    }

    /**
     * The lists by path
     *
     * @return array<string, CollectionInterface>
     */
    private function byPath(): array
    {
        if ($this->byPath === null) {
            $this->byPath = [];
            foreach ($this->collections as $collection) {
                $this->byPath[$collection->getPath()] = $collection;
            }
        }

        return $this->byPath;
    }
}
