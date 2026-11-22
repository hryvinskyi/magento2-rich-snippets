<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Snippet;

use Hryvinskyi\RichSnippets\Api\Data\SnippetInterface;
use Hryvinskyi\RichSnippets\Api\Exception\SnippetRuleException;

/**
 * Writes snippets, inside transactions that can run work once they are committed
 */
interface SnippetWriterInterface
{
    /**
     * Run work in a transaction: committed when the work returns, rolled back when it throws
     *
     * A transaction run inside another is part of the outer one: nothing is committed, and no work registered with
     * afterCommit() runs, before the outermost transaction commits.
     *
     * @template T
     * @param callable():T $work
     * @return T what the work returned
     */
    public function inTransaction(callable $work): mixed;

    /**
     * Run work once the current transaction is committed; nothing runs when it is rolled back
     *
     * @param callable():void $work
     * @return void
     */
    public function afterCommit(callable $work): void;

    /**
     * Store a new snippet
     *
     * @param SnippetInterface $snippet
     * @param string $author the user name of the admin who saves it; empty for none
     * @return int the new snippet's ID
     * @throws SnippetRuleException when another snippet has the code
     */
    public function insert(SnippetInterface $snippet, string $author): int;

    /**
     * Store a snippet over the stored one with its ID
     *
     * Every write moves the stored snippet's update time forward, even one that changes nothing else and one in the
     * same second as the write before, so the time a reader saw tells whether the snippet was written since.
     *
     * @param SnippetInterface $snippet a snippet with an ID
     * @param string $author the user name of the admin who saves it; empty for none
     * @param string|null $expectedUpdatedAt write only while the stored snippet's update time is still this value;
     *     null writes whatever it is
     * @return bool whether the snippet was written: false when it is not stored or its update time differs
     */
    public function update(SnippetInterface $snippet, string $author, ?string $expectedUpdatedAt = null): bool;

    /**
     * Delete the stored snippet with an ID
     *
     * @param int $id
     * @return void
     */
    public function remove(int $id): void;
}
