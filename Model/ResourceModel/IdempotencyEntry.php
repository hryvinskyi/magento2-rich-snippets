<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\ResourceModel;

use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

/**
 * The table of creating writes by idempotency key, with the answer each first gave
 *
 * The unique index on the key is what lets only one write claim a key: a second insert of the key waits for the
 * first write's transaction and fails once it commits.
 */
class IdempotencyEntry extends AbstractDb
{
    public const TABLE = 'hryvinskyi_rich_snippets_idempotency';

    public const ID = 'entry_id';
    public const KEY = 'idempotency_key';
    public const ACTION = 'action';
    public const RESULT = 'result';

    private const PENDING_RESULT = '{}';

    /**
     * Bind the resource to its table
     *
     * @return void
     */
    protected function _construct(): void
    {
        $this->_init(self::TABLE, self::ID);
    }

    /**
     * The entry of a key: its action and the answer stored for it
     *
     * @param string $key
     * @return array{action: string, result: string}|null
     */
    public function fetchByKey(string $key): ?array
    {
        $connection = $this->connection();
        $row = $connection->fetchRow(
            $connection->select()
                ->from($this->getMainTable(), [self::ACTION, self::RESULT])
                ->where(self::KEY . ' = ?', $key)
        );
        $action = is_array($row) ? ($row[self::ACTION] ?? null) : null;
        $result = is_array($row) ? ($row[self::RESULT] ?? null) : null;

        return is_string($action) && is_string($result) ? ['action' => $action, 'result' => $result] : null;
    }

    /**
     * Claim a key for an action, before the write runs
     *
     * @param string $key
     * @param string $action
     * @return void
     * @throws \Magento\Framework\DB\Adapter\DuplicateException when another write claimed the key
     */
    public function claim(string $key, string $action): void
    {
        $this->connection()->insert(
            $this->getMainTable(),
            [self::KEY => $key, self::ACTION => $action, self::RESULT => self::PENDING_RESULT]
        );
    }

    /**
     * Store the answer of the write that claimed a key
     *
     * @param string $key
     * @param string $result the answer, as JSON
     * @return void
     */
    public function record(string $key, string $result): void
    {
        $this->connection()->update($this->getMainTable(), [self::RESULT => $result], [self::KEY . ' = ?' => $key]);
    }

    /**
     * The table's connection
     *
     * @return AdapterInterface
     */
    private function connection(): AdapterInterface
    {
        return $this->getConnection() ?: throw new \LogicException('The idempotency table has no database connection.');
    }
}
