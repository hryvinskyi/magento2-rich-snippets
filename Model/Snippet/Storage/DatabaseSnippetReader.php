<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Snippet\Storage;

use Hryvinskyi\RichSnippets\Model\ResourceModel\Snippet as SnippetResource;
use Hryvinskyi\RichSnippets\Model\Snippet\SnippetReaderInterface;
use Hryvinskyi\RichSnippets\Model\Snippet\SnippetRootInterface;

/**
 * Reads snippets from the snippet table
 */
class DatabaseSnippetReader implements SnippetReaderInterface
{
    /**
     * @param SnippetResource $resource
     * @param SnippetRowMapper $mapper
     */
    public function __construct(
        private readonly SnippetResource $resource,
        private readonly SnippetRowMapper $mapper
    ) {
    }

    /**
     * @inheritDoc
     */
    public function byId(int $id): ?SnippetRootInterface
    {
        $row = $this->resource->fetchById($id);

        return $row === null ? null : $this->mapper->toSnippet($row);
    }

    /**
     * @inheritDoc
     */
    public function byCode(string $code): ?SnippetRootInterface
    {
        $row = $this->resource->fetchByCode($code);

        return $row === null ? null : $this->mapper->toSnippet($row);
    }

    /**
     * @inheritDoc
     */
    public function byCodes(array $codes): array
    {
        return array_map(
            fn (array $row): SnippetRootInterface => $this->mapper->toSnippet($row),
            $this->resource->fetchByCodes($codes)
        );
    }

    /**
     * @inheritDoc
     */
    public function matching(?int $storeId, ?string $pageKind, ?bool $active, ?bool $block): array
    {
        return array_map(
            fn (array $row): SnippetRootInterface => $this->mapper->toSnippet($row),
            $this->resource->fetchMatching($storeId, $pageKind, $active, $block)
        );
    }

    /**
     * @inheritDoc
     */
    public function codesStartingWith(string $prefix): array
    {
        return $this->resource->codesStartingWith($prefix);
    }
}
