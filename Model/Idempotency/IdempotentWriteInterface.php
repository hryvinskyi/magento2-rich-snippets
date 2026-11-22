<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Idempotency;

use Hryvinskyi\RichSnippets\Api\Exception\IdempotencyException;

/**
 * Runs a creating write once per idempotency key
 *
 * The admin page generates a key once per intent of the person (one click on "Add"), and every retry of that intent
 * carries it. The first request runs the write and stores its answer with the key, in the write's own transaction;
 * a request repeated with the key runs nothing and gets the stored answer. A write that fails stores nothing, so the
 * key can be tried again.
 *
 * A write comes in two steps. The preparation runs only for a key not used yet, outside any transaction: it checks
 * the request and refuses a bad one before anything is claimed, and returns the write. The write runs inside the
 * transaction that claims the key.
 */
interface IdempotentWriteInterface
{
    /**
     * Run a write once for a key, answering what it answered the first time
     *
     * @param string $key up to 64 letters, digits, `-`, `_`, `.` or `:`
     * @param string $action what kind of write the key is for (`library_add`), up to 32 characters
     * @param callable():(callable():array<mixed>) $prepare checks the request and returns the write; what the write
     *     returns must survive a JSON round trip
     * @return array<mixed> the write's answer, or the one stored for the key
     * @throws IdempotencyException when the key is invalid, or was used for another action
     */
    public function once(string $key, string $action, callable $prepare): array;
}
