<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Idempotency;

use Hryvinskyi\RichSnippets\Api\Exception\IdempotencyException;
use Hryvinskyi\RichSnippets\Api\Exception\IdempotencyReason;
use Hryvinskyi\RichSnippets\Model\ResourceModel\IdempotencyEntry;
use Hryvinskyi\RichSnippets\Model\Snippet\SnippetWriterInterface;
use Magento\Framework\DB\Adapter\DuplicateException;
use Magento\Framework\Serialize\SerializerInterface;

/**
 * Runs a write once per key, claiming the key in the same transaction as the write
 *
 * A key with a stored answer is answered before anything else runs. Otherwise the request is prepared outside any
 * transaction, and the key is claimed by inserting it before the write runs, so a second request with the key waits
 * on the unique index until the first transaction ends: once it commits, the second request's insert fails, its
 * transaction rolls back and it answers the stored result; if the first rolls back, the second runs the write
 * itself. The transaction is the snippet writer's, so the snippets the write saves commit together with the key and
 * their after-commit work runs once.
 */
class DatabaseIdempotentWrite implements IdempotentWriteInterface
{
    private const KEY_PATTERN = '/\A[A-Za-z0-9][A-Za-z0-9_.:-]{0,63}\z/';
    private const ACTION_MAX_LENGTH = 32;

    /**
     * @param IdempotencyEntry $entries
     * @param SnippetWriterInterface $writer
     * @param SerializerInterface $json
     */
    public function __construct(
        private readonly IdempotencyEntry $entries,
        private readonly SnippetWriterInterface $writer,
        private readonly SerializerInterface $json
    ) {
    }

    /**
     * @inheritDoc
     */
    public function once(string $key, string $action, callable $prepare): array
    {
        if (preg_match(self::KEY_PATTERN, $key) !== 1) {
            throw new IdempotencyException(IdempotencyReason::KeyInvalid);
        }
        if ($action === '' || strlen($action) > self::ACTION_MAX_LENGTH) {
            throw new \InvalidArgumentException('An idempotent write names its action in 1 to 32 characters.');
        }
        $stored = $this->stored($key, $action);
        if ($stored !== null) {
            return $stored;
        }
        $write = $prepare();
        try {
            return $this->writer->inTransaction(function () use ($key, $action, $write): array {
                $stored = $this->stored($key, $action);
                if ($stored !== null) {
                    return $stored;
                }
                $this->entries->claim($key, $action);
                $result = $write();
                $this->entries->record($key, (string) $this->json->serialize($result));

                return $result;
            });
        } catch (DuplicateException) {
            return $this->stored($key, $action) ?? throw new \LogicException(
                sprintf('The idempotency key "%s" was claimed but holds no answer.', $key)
            );
        }
    }

    /**
     * The answer stored for a key; null when the key is unused
     *
     * @param string $key
     * @param string $action
     * @return array<mixed>|null
     * @throws IdempotencyException when the key was used for another action
     */
    private function stored(string $key, string $action): ?array
    {
        $entry = $this->entries->fetchByKey($key);
        if ($entry === null) {
            return null;
        }
        if ($entry['action'] !== $action) {
            throw new IdempotencyException(IdempotencyReason::KeyReused, ['key' => $key, 'action' => $action]);
        }
        $result = $this->json->unserialize($entry['result']);

        return is_array($result) ? $result : [];
    }
}
