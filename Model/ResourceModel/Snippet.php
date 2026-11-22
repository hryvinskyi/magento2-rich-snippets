<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\ResourceModel;

use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Sql\ExpressionFactory;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;
use Magento\Framework\Model\ResourceModel\Db\Context;

/**
 * Stores snippets, one row each
 *
 * The rows are plain arrays: the snippet itself is a domain object mapped to and from them outside this class. The
 * list columns hold JSON; the timestamps are the database's own. Every write over a row moves its `updated_at`
 * forward, by at least one second when two writes fall in the same second, so a reader that saw one value can tell
 * whether the row was written since.
 */
class Snippet extends AbstractDb
{
    public const TABLE = 'hryvinskyi_rich_snippets_snippet';

    public const ID = 'snippet_id';
    public const CODE = 'code';
    public const NAME = 'name';
    public const TYPE = 'type';
    public const IS_BLOCK = 'is_block';
    public const ACTIVE = 'active';
    public const PAGE_KINDS = 'page_kinds';
    public const CONDITIONS = 'conditions';
    public const STORE_IDS = 'store_ids';
    public const TEMPLATE = 'template';
    public const ORIGIN_CODE = 'origin_code';
    public const ORIGIN_VERSION = 'origin_version';
    public const SORT_ORDER = 'sort_order';
    public const CREATED_AT = 'created_at';
    public const UPDATED_AT = 'updated_at';
    public const UPDATED_BY = 'updated_by';

    private const ALL_STORES_JSON = '0';
    private const NEXT_UPDATED_AT = 'GREATEST(CURRENT_TIMESTAMP, %s + INTERVAL 1 SECOND)';

    /**
     * @param Context $context
     * @param ExpressionFactory $expressions
     * @param string|null $connectionName
     */
    public function __construct(
        Context $context,
        private readonly ExpressionFactory $expressions,
        ?string $connectionName = null
    ) {
        parent::__construct($context, $connectionName);
    }

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
     * The row with an ID
     *
     * @param int $id
     * @return array<string, mixed>|null
     */
    public function fetchById(int $id): ?array
    {
        $connection = $this->connection();
        $row = $connection->fetchRow(
            $connection->select()->from($this->getMainTable())->where(self::ID . ' = ?', $id)
        );

        return is_array($row) && $row !== [] ? $this->stringKeyed($row) : null;
    }

    /**
     * The row with a code
     *
     * @param string $code
     * @return array<string, mixed>|null
     */
    public function fetchByCode(string $code): ?array
    {
        $connection = $this->connection();
        $row = $connection->fetchRow(
            $connection->select()->from($this->getMainTable())->where(self::CODE . ' = ?', $code)
        );

        return is_array($row) && $row !== [] ? $this->stringKeyed($row) : null;
    }

    /**
     * When the earliest of some rows was added, as the database keeps it
     *
     * @param list<int> $ids
     * @return string|null null when none of the IDs is stored
     */
    public function firstCreatedAt(array $ids): ?string
    {
        if ($ids === []) {
            return null;
        }
        $connection = $this->connection();
        // no row, or no ID among the stored ones, reads as an empty text
        $first = (string) $connection->fetchOne(
            $connection->select()
                ->from($this->getMainTable(), ['first' => 'MIN(' . self::CREATED_AT . ')'])
                ->where(self::ID . ' IN (?)', $ids)
        );

        return $first !== '' ? $first : null;
    }

    /**
     * The rows with any of the codes, in code order
     *
     * @param list<string> $codes
     * @return list<array<string, mixed>>
     */
    public function fetchByCodes(array $codes): array
    {
        if ($codes === []) {
            return [];
        }
        $connection = $this->connection();
        $select = $connection->select()
            ->from($this->getMainTable())
            ->where(self::CODE . ' IN (?)', $codes)
            ->order(self::CODE . ' ASC');

        return $this->rows($connection->fetchAll($select));
    }

    /**
     * The rows that meet every criterion given, by sort order, then ID
     *
     * @param int|null $storeId rows listing the store view or every store view
     * @param string|null $pageKind rows listing the kind of page
     * @param bool|null $active
     * @param bool|null $block
     * @return list<array<string, mixed>>
     */
    public function fetchMatching(?int $storeId, ?string $pageKind, ?bool $active, ?bool $block): array
    {
        $connection = $this->connection();
        $select = $connection->select()
            ->from($this->getMainTable())
            ->order([self::SORT_ORDER . ' ASC', self::ID . ' ASC']);
        if ($storeId !== null) {
            $select->where(
                'JSON_CONTAINS(' . self::STORE_IDS . ', ?) OR JSON_CONTAINS(' . self::STORE_IDS . ', \''
                    . self::ALL_STORES_JSON . '\')',
                (string) $storeId
            );
        }
        if ($pageKind !== null) {
            $select->where('JSON_CONTAINS(' . self::PAGE_KINDS . ', ?)', json_encode($pageKind, JSON_THROW_ON_ERROR));
        }
        if ($active !== null) {
            $select->where(self::ACTIVE . ' = ?', (int) $active);
        }
        if ($block !== null) {
            $select->where(self::IS_BLOCK . ' = ?', (int) $block);
        }

        return $this->rows($connection->fetchAll($select));
    }

    /**
     * Every stored template, by code
     *
     * @return array<string, string>
     */
    public function templatesByCode(): array
    {
        $connection = $this->connection();
        $pairs = $connection->fetchPairs(
            $connection->select()->from($this->getMainTable(), [self::CODE, self::TEMPLATE])
        );
        $templates = [];
        foreach ($pairs as $code => $template) {
            $templates[(string) $code] = is_string($template) ? $template : '';
        }

        return $templates;
    }

    /**
     * The stored codes that start with a prefix
     *
     * @param string $prefix
     * @return list<string>
     */
    public function codesStartingWith(string $prefix): array
    {
        $connection = $this->connection();
        $select = $connection->select()
            ->from($this->getMainTable(), [self::CODE])
            ->where(self::CODE . ' LIKE ?', str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $prefix) . '%');
        $codes = [];
        foreach ($connection->fetchCol($select) as $code) {
            if (is_string($code)) {
                $codes[] = $code;
            }
        }

        return $codes;
    }

    /**
     * Insert a row
     *
     * The new row is found again by its code, which the unique index keeps to one row.
     *
     * @param array<string,scalar|null> $row a row with a code
     * @return int the new row's ID
     */
    public function insertRow(array $row): int
    {
        $connection = $this->connection();
        $connection->insert($this->getMainTable(), $row);
        $id = $connection->fetchOne(
            $connection->select()
                ->from($this->getMainTable(), [self::ID])
                ->where(self::CODE . ' = ?', (string) ($row[self::CODE] ?? ''))
        );

        return is_numeric($id) ? (int) $id : throw new \LogicException('The inserted snippet cannot be found again.');
    }

    /**
     * Write a row over the one with an ID, moving its `updated_at` forward
     *
     * @param int $id
     * @param array<string,scalar|null> $row
     * @param string|null $expectedUpdatedAt write only when the row's `updated_at` is still this value; null writes
     *     whatever it is
     * @return bool whether a row was written: false when no row has the ID or its `updated_at` differs
     */
    public function updateRow(int $id, array $row, ?string $expectedUpdatedAt = null): bool
    {
        $connection = $this->connection();
        $values = $row;
        $values[self::UPDATED_AT] = $this->expressions->create([
            'expression' => sprintf(self::NEXT_UPDATED_AT, $connection->quoteIdentifier(self::UPDATED_AT)),
        ]);
        $where = [self::ID . ' = ?' => $id];
        if ($expectedUpdatedAt !== null) {
            $where[self::UPDATED_AT . ' = ?'] = $expectedUpdatedAt;
        }

        return $connection->update($this->getMainTable(), $values, $where) > 0;
    }

    /**
     * Delete the row with an ID
     *
     * @param int $id
     * @return void
     */
    public function deleteRow(int $id): void
    {
        $this->connection()->delete($this->getMainTable(), [self::ID . ' = ?' => $id]);
    }

    /**
     * The connection the table is read and written through
     *
     * @return AdapterInterface
     */
    private function connection(): AdapterInterface
    {
        return $this->getConnection() ?: throw new \LogicException('The snippet table has no database connection.');
    }

    /**
     * The rows of a fetch, each keyed by column name
     *
     * @param array<mixed> $rows
     * @return list<array<string, mixed>>
     */
    private function rows(array $rows): array
    {
        $keyed = [];
        foreach ($rows as $row) {
            if (is_array($row)) {
                $keyed[] = $this->stringKeyed($row);
            }
        }

        return $keyed;
    }

    /**
     * A row keyed by column name
     *
     * @param array<mixed> $row
     * @return array<string, mixed>
     */
    private function stringKeyed(array $row): array
    {
        $keyed = [];
        foreach ($row as $column => $value) {
            $keyed[(string) $column] = $value;
        }

        return $keyed;
    }
}
