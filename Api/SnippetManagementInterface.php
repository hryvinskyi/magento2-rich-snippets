<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Api;

use Hryvinskyi\JsonTemplate\Api\Exception\CompileException;
use Hryvinskyi\RichSnippets\Api\Data\SnippetInterface;
use Hryvinskyi\RichSnippets\Api\Exception\IdempotencyException;
use Hryvinskyi\RichSnippets\Api\Exception\SnippetInUseException;
use Hryvinskyi\RichSnippets\Api\Exception\SnippetRuleException;
use Magento\Framework\Exception\NoSuchEntityException;

/**
 * What the Snippets page does to one snippet: switch it, copy it, delete it
 *
 * The switch takes the state wanted, never "the other one", so a repeated request changes nothing more. A copy is
 * named "{name} (copy)", with a number when that name is taken, gets a code of its own, is placed like the original
 * just after it and arrives switched off; a copy carries an idempotency key generated once per intent, so a request
 * repeated with it creates nothing more and answers the first copy. A delete is refused while another snippet
 * includes the snippet. Every change cleans the page cache of the pages it reaches once it is committed.
 *
 * @api
 */
interface SnippetManagementInterface
{
    /**
     * Switch a page snippet on or off
     *
     * @param int $id
     * @param bool $active
     * @return SnippetInterface the snippet as stored
     * @throws NoSuchEntityException when no snippet has the ID
     * @throws SnippetRuleException when a building block is to be switched on
     */
    public function setActive(int $id, bool $active): SnippetInterface;

    /**
     * Add a switched-off copy of a snippet
     *
     * @param int $id the original's ID
     * @param string $idempotencyKey
     * @return SnippetInterface the copy as stored
     * @throws NoSuchEntityException when no snippet has the ID
     * @throws IdempotencyException when the key is invalid or was used for another kind of write
     * @throws SnippetRuleException|CompileException when the copy breaks a snippet rule
     */
    public function duplicate(int $id, string $idempotencyKey): SnippetInterface;

    /**
     * Delete a snippet
     *
     * @param int $id
     * @return void
     * @throws NoSuchEntityException when no snippet has the ID
     * @throws SnippetInUseException when another snippet includes it, naming those snippets
     */
    public function delete(int $id): void;
}
