<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Selection;

use Hryvinskyi\RichSnippets\Model\ResourceModel\Snippet as SnippetResource;
use Hryvinskyi\RichSnippets\Model\Snippet\Storage\SnippetRowMapper;
use Magento\Framework\Cache\CacheConstants;
use Magento\Framework\Cache\FrontendInterface;
use Magento\Framework\ObjectManager\ResetAfterRequestInterface;
use Magento\Framework\Serialize\SerializerInterface;

/**
 * The selection index of each store view, kept in the module's cache type and built from the stored snippets on the
 * first page that needs it
 *
 * One entry per store view holds the rows of its switched-on page snippets and, for each kind of page, their IDs by
 * sort order. The entry carries a tag of its own store view and no expiry: every snippet write forgets the entries of
 * the store views it can change once it is committed, and "Flush" in Cache Management forgets them all. With the
 * cache type disabled nothing is kept between requests.
 *
 * A build that read its rows before a write committed must never outlive that write. Each store view therefore has a
 * generation, a random stamp kept beside the entries: forgetting a store view's selection draws a new generation
 * first, then drops its entry. An entry carries the generation it was built under and is read only while that is
 * still the current one; a build keeps its entry only when the generation it started under is still current after
 * the rows were read. A write that commits between the read of the rows and the keeping of the entry thus leaves
 * either no entry or one whose generation is no longer current, and the next page builds again. A generation that
 * is lost (a flush, an eviction) counts as changed.
 *
 * Each store view's index is also remembered for the rest of the request (or the console run), under the generation
 * that was current when it was built or read, so a batch of pages costs one read of the stored snippets per store
 * view even with the cache type disabled. It is used only while that generation is still the current one: a write
 * anywhere draws a new generation, and the next page builds again. With the cache type disabled no generation is
 * kept, so a write in this process forgets what the process remembers, and a write in another process is seen from
 * the next request or run on. What is remembered is dropped between requests.
 *
 * @phpstan-type Index array{kinds: array<string, list<int>>, rows: array<int, array<string, mixed>>}
 */
class CachedSelectionIndex implements
    SelectionIndexInterface,
    SelectionIndexInvalidatorInterface,
    ResetAfterRequestInterface
{
    private const PREFIX = 'rich_snippets_selection_';
    private const GENERATION_PREFIX = 'rich_snippets_selection_generation_';
    private const GENERATION = 'generation';
    private const KINDS = 'kinds';
    private const ROWS = 'rows';
    private const INDEX = 'index';
    private const GENERATION_BYTES = 16;

    /**
     * @var array<int,array{generation: string|null,index: Index}> by store ID, what this request built or read
     */
    private array $remembered = [];

    /**
     * @param FrontendInterface $cache the module's cache type
     * @param SnippetResource $resource
     * @param SnippetRowMapper $mapper
     * @param SerializerInterface $json
     */
    public function __construct(
        private readonly FrontendInterface $cache,
        private readonly SnippetResource $resource,
        private readonly SnippetRowMapper $mapper,
        private readonly SerializerInterface $json
    ) {
    }

    /**
     * @inheritDoc
     */
    public function snippetsFor(string $kind, int $storeId): array
    {
        $index = $this->index($storeId);
        $snippets = [];
        foreach ($index[self::KINDS][$kind] ?? [] as $id) {
            $row = $index[self::ROWS][$id] ?? null;
            if ($row !== null) {
                $snippets[] = $this->mapper->toSnippet($row);
            }
        }

        return $snippets;
    }

    /**
     * @inheritDoc
     */
    public function invalidate(array $storeIds): void
    {
        if ($storeIds === []) {
            return;
        }
        foreach ($storeIds as $storeId) {
            unset($this->remembered[$storeId]);
            $this->advance($storeId);
        }
        $this->cache->clean(
            CacheConstants::CLEANING_MODE_MATCHING_ANY_TAG,
            array_map(static fn (int $storeId): string => self::PREFIX . $storeId, $storeIds)
        );
    }

    /**
     * @inheritDoc
     */
    public function _resetState(): void
    {
        $this->remembered = [];
    }

    /**
     * A store view's index
     *
     * The one this request has under the current generation, else the one kept under it, else one built from the
     * stored snippets.
     *
     * @param int $storeId
     * @return Index
     */
    private function index(int $storeId): array
    {
        $generation = $this->generation($storeId);
        $remembered = $this->remembered[$storeId] ?? null;
        if ($remembered !== null && $remembered[self::GENERATION] === $generation) {
            return $remembered[self::INDEX];
        }
        $kept = $generation === null ? null : $this->load($storeId, $generation);
        if ($kept !== null) {
            $this->remembered[$storeId] = [self::GENERATION => $generation, self::INDEX => $kept];

            return $kept;
        }

        return $this->build($storeId, $generation);
    }

    /**
     * Build a store view's index from the stored snippets; keep and remember it while its generation holds
     *
     * The index is neither kept nor remembered when the store view's selection was forgotten while the rows were
     * read. With no generation kept before or after the read, as with the cache type disabled, it is remembered
     * under none.
     *
     * @param int $storeId
     * @param string|null $current the store view's generation before the build; null when none is kept
     * @return Index
     */
    private function build(int $storeId, ?string $current): array
    {
        $generation = $current ?? $this->advance($storeId);
        $index = [self::KINDS => [], self::ROWS => []];
        foreach ($this->resource->fetchMatching($storeId, null, true, false) as $row) {
            $snippet = $this->mapper->toSnippet($row);
            $id = (int) $snippet->getId();
            $index[self::ROWS][$id] = $row;
            foreach ($snippet->getPageKinds() as $kind) {
                $index[self::KINDS][$kind][] = $id;
            }
        }
        $after = $this->generation($storeId);
        unset($this->remembered[$storeId]);
        if ($after === $generation) {
            $this->cache->save(
                (string) $this->json->serialize([self::GENERATION => $generation] + $index),
                self::PREFIX . $storeId,
                [self::PREFIX . $storeId]
            );
        }
        if ($after === $generation || ($after === null && $current === null)) {
            $this->remembered[$storeId] = [self::GENERATION => $after, self::INDEX => $index];
        }

        return $index;
    }

    /**
     * The store view's current generation; null when none is kept
     *
     * @param int $storeId
     * @return string|null
     */
    private function generation(int $storeId): ?string
    {
        $generation = $this->cache->load(self::GENERATION_PREFIX . $storeId);

        return is_string($generation) && $generation !== '' ? $generation : null;
    }

    /**
     * Draw a new generation for the store view and keep it
     *
     * @param int $storeId
     * @return string the new generation
     */
    private function advance(int $storeId): string
    {
        $generation = bin2hex(random_bytes(self::GENERATION_BYTES));
        $this->cache->save($generation, self::GENERATION_PREFIX . $storeId);

        return $generation;
    }

    /**
     * The index kept for a store view under its current generation
     *
     * Null when none is kept, it was built under another generation or it cannot be read.
     *
     * @param int $storeId
     * @param string $generation the store view's current generation
     * @return Index|null
     */
    private function load(int $storeId, string $generation): ?array
    {
        $stored = $this->cache->load(self::PREFIX . $storeId);
        if (!is_string($stored) || $stored === '') {
            return null;
        }
        try {
            $index = $this->json->unserialize($stored);
        } catch (\InvalidArgumentException) {
            return null;
        }
        if (!is_array($index)
            || ($index[self::GENERATION] ?? null) !== $generation
            || !is_array($index[self::KINDS] ?? null)
            || !is_array($index[self::ROWS] ?? null)
        ) {
            return null;
        }

        return [self::KINDS => $this->kinds($index[self::KINDS]), self::ROWS => $this->rows($index[self::ROWS])];
    }

    /**
     * The kept snippet IDs by kind
     *
     * @param array<mixed> $kinds
     * @return array<string,list<int>>
     */
    private function kinds(array $kinds): array
    {
        $typed = [];
        foreach ($kinds as $kind => $ids) {
            $typed[(string) $kind] = is_array($ids)
                ? array_values(array_map(static fn (mixed $id): int => is_numeric($id) ? (int) $id : 0, $ids))
                : [];
        }

        return $typed;
    }

    /**
     * The kept rows by snippet ID
     *
     * @param array<mixed> $rows
     * @return array<int,array<string,mixed>>
     */
    private function rows(array $rows): array
    {
        $typed = [];
        foreach ($rows as $id => $row) {
            if (!is_array($row)) {
                continue;
            }
            $columns = [];
            foreach ($row as $column => $value) {
                $columns[(string) $column] = $value;
            }
            $typed[(int) $id] = $columns;
        }

        return $typed;
    }
}
