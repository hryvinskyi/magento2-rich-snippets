<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Snippet\Storage;

use Hryvinskyi\RichSnippets\Api\Data\SnippetInterface;
use Hryvinskyi\RichSnippets\Api\Exception\SnippetRuleException;
use Hryvinskyi\RichSnippets\Api\Exception\SnippetRuleReason;
use Hryvinskyi\RichSnippets\Model\ResourceModel\Snippet as SnippetResource;
use Hryvinskyi\RichSnippets\Model\Snippet\SnippetWriterInterface;
use Magento\Framework\DB\Adapter\DuplicateException;

/**
 * Writes snippets to the snippet table, in the resource's transactions
 *
 * Work registered to run after a commit is the resource's commit callback: it runs when the outermost transaction
 * on the connection commits through a resource model, and is dropped when a transaction is rolled back. The unique
 * index on the code is what refuses a code another snippet has, so two creations racing for one code cannot both
 * be stored.
 */
class DatabaseSnippetWriter implements SnippetWriterInterface
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
    public function inTransaction(callable $work): mixed
    {
        $this->resource->beginTransaction();
        try {
            $result = $work();
            $this->resource->commit();
        } catch (\Throwable $e) {
            $this->resource->rollBack();
            throw $e;
        }

        return $result;
    }

    /**
     * @inheritDoc
     */
    public function afterCommit(callable $work): void
    {
        $this->resource->addCommitCallback($work);
    }

    /**
     * @inheritDoc
     */
    public function insert(SnippetInterface $snippet, string $author): int
    {
        try {
            return $this->resource->insertRow($this->mapper->toRow($snippet, $author));
        } catch (DuplicateException $e) {
            throw new SnippetRuleException(SnippetRuleReason::CodeTaken, ['code' => $snippet->getCode()], $e);
        }
    }

    /**
     * @inheritDoc
     */
    public function update(SnippetInterface $snippet, string $author, ?string $expectedUpdatedAt = null): bool
    {
        $id = $snippet->getId();
        if ($id === null) {
            throw new \InvalidArgumentException('Only a stored snippet can be written over.');
        }

        return $this->resource->updateRow($id, $this->mapper->toRow($snippet, $author), $expectedUpdatedAt);
    }

    /**
     * @inheritDoc
     */
    public function remove(int $id): void
    {
        $this->resource->deleteRow($id);
    }
}
