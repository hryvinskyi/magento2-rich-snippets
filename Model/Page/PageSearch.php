<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Page;

use Hryvinskyi\RichSnippets\Api\Data\ConditionTreeInterface;
use Hryvinskyi\RichSnippets\Api\Data\PageEntityInterface;
use Hryvinskyi\RichSnippets\Api\Data\PageMatchInterface;
use Hryvinskyi\RichSnippets\Api\Data\PageMatchInterfaceFactory;
use Hryvinskyi\RichSnippets\Api\ExistingPagesInterface;
use Hryvinskyi\RichSnippets\Api\PageEntityFactoryInterface;
use Hryvinskyi\RichSnippets\Api\PageKindPoolInterface;
use Hryvinskyi\RichSnippets\Api\PageSearchInterface;
use Hryvinskyi\RichSnippets\Model\Page\Search\PageSearchSourceInterface;
use Hryvinskyi\RichSnippets\Model\Placement\PlacementConditions;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Select;

/**
 * Finds existing pages through one source per kind listed in the container configuration
 *
 * A kind with no source, or whose pages cannot be listed, has nothing to find. An item that is not a source is a
 * configuration error, refused when the search is built. The pages that meet some conditions are told apart in the
 * database, by the same predicate the placement counts pages with.
 */
class PageSearch implements PageSearchInterface
{
    private const PAGES = 'pages';
    private const ENTITY_ID = ExistingPagesInterface::ENTITY_ID;
    private const ID_COLUMN = self::PAGES . '.' . self::ENTITY_ID;

    /**
     * @var array<string, PageSearchSourceInterface>
     */
    private array $sources = [];

    /**
     * @param PageKindPoolInterface $kinds
     * @param ExistingPagesInterface $pages
     * @param PageEntityFactoryInterface $pageFactory
     * @param PageMatchInterfaceFactory $matches
     * @param ResourceConnection $resource
     * @param PlacementConditions $conditions
     * @param array<array-key,mixed> $sources by kind code
     * @throws \InvalidArgumentException
     */
    public function __construct(
        private readonly PageKindPoolInterface $kinds,
        private readonly ExistingPagesInterface $pages,
        private readonly PageEntityFactoryInterface $pageFactory,
        private readonly PageMatchInterfaceFactory $matches,
        private readonly ResourceConnection $resource,
        private readonly PlacementConditions $conditions,
        array $sources = []
    ) {
        foreach ($sources as $kind => $source) {
            if (!$source instanceof PageSearchSourceInterface) {
                throw new \InvalidArgumentException(sprintf('The pages of the kind "%s" have no search.', $kind));
            }
            $this->sources[(string) $kind] = $source;
        }
    }

    /**
     * @inheritDoc
     */
    public function search(string $query, int $storeId, array $kinds, int $limit): array
    {
        $found = [];
        foreach ($this->searched($kinds, $limit) as $kind => $source) {
            $pages = $this->pages->select($kind, $storeId);
            if ($pages !== null) {
                $matched = $this->matched($kind, $source, $this->existing($pages), trim($query), $storeId, $limit);
                array_push($found, ...$matched);
            }
        }

        return $found;
    }

    /**
     * @inheritDoc
     */
    public function searchMeeting(
        string $query,
        int $storeId,
        array $kinds,
        int $limit,
        ConditionTreeInterface $conditions
    ): array {
        $meeting = [];
        $others = [];
        foreach ($this->searched($kinds, $limit) as $kind => $source) {
            $pages = $this->pages->select($kind, $storeId);
            if ($pages === null) {
                continue;
            }
            $predicate = $this->conditions->predicate($conditions, $kind, self::ID_COLUMN, $storeId);
            $first = $this->existing($pages);
            if ($predicate !== null) {
                $first->where($predicate, null, Select::TYPE_CONDITION);
            }
            $met = $this->matched($kind, $source, $first, trim($query), $storeId, $limit);
            array_push($meeting, ...$met);
            if ($predicate === null || count($met) >= $limit) {
                continue;
            }
            $rest = $this->existing($pages);
            $ids = array_values(array_filter(
                array_map(static fn (PageMatchInterface $match): ?int => $match->getPage()->getEntityId(), $met),
                static fn (?int $id): bool => $id !== null
            ));
            if ($ids !== []) {
                $rest->where(self::ID_COLUMN . ' NOT IN (?)', $ids);
            }
            $other = $this->matched($kind, $source, $rest, trim($query), $storeId, $limit - count($met));
            array_push($others, ...$other);
        }

        return [...$meeting, ...$others];
    }

    /**
     * The sources of the kinds a search looks in, in the order asked, each kind once
     *
     * @param list<string> $kinds
     * @param int $limit
     * @return array<string, PageSearchSourceInterface>
     * @throws \InvalidArgumentException when no kind of page has one of the codes, or the limit is below 1
     */
    private function searched(array $kinds, int $limit): array
    {
        if ($limit < 1) {
            throw new \InvalidArgumentException('A page search returns at least one page of each kind.');
        }
        $sources = [];
        foreach (array_values(array_unique($kinds)) as $kind) {
            if (!$this->kinds->has($kind)) {
                throw new \InvalidArgumentException(sprintf('No kind of page has the code "%s".', $kind));
            }
            if (isset($this->sources[$kind])) {
                $sources[$kind] = $this->sources[$kind];
            }
        }

        return $sources;
    }

    /**
     * @inheritDoc
     */
    public function find(PageEntityInterface $page): ?PageMatchInterface
    {
        $kind = $page->getKind();
        $id = $page->getEntityId();
        $source = $this->sources[$kind] ?? null;
        $pages = $this->kinds->has($kind) ? $this->pages->select($kind, $page->getStoreId()) : null;
        if ($id === null || $source === null || $pages === null) {
            return null;
        }
        $one = $this->existing($pages)->where(self::ID_COLUMN . ' = ?', $id);

        return $this->matched($kind, $source, $one, '', $page->getStoreId(), 1)[0] ?? null;
    }

    /**
     * The pages a source finds, named and addressed
     *
     * @param string $kind
     * @param PageSearchSourceInterface $source
     * @param Select $pages
     * @param string $query
     * @param int $storeId
     * @param int $limit
     * @return list<PageMatchInterface>
     */
    private function matched(
        string $kind,
        PageSearchSourceInterface $source,
        Select $pages,
        string $query,
        int $storeId,
        int $limit
    ): array {
        $names = $source->names($pages, $query, $storeId, $limit);
        $urls = $source->urls(array_keys($names), $storeId);
        $matches = [];
        foreach ($names as $id => $name) {
            $matches[] = $this->matches->create([
                'page' => $this->pageFactory->create($kind, $id, $storeId),
                'label' => $name,
                'url' => $urls[$id] ?? '',
            ]);
        }

        return $matches;
    }

    /**
     * The existing pages as a select with only their entity ID, which a source may join onto
     *
     * @param Select $pages
     * @return Select
     */
    private function existing(Select $pages): Select
    {
        return $this->resource->getConnection()->select()->from(
            [self::PAGES => $pages],
            [self::ENTITY_ID => self::ID_COLUMN]
        );
    }
}
