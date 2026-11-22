<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Transfer;

use Hryvinskyi\RichSnippets\Api\Data\SnippetExportFileInterface;
use Hryvinskyi\RichSnippets\Api\Data\SnippetInterface;
use Hryvinskyi\RichSnippets\Api\SnippetExportInterface;
use Hryvinskyi\RichSnippets\Api\SnippetRepositoryInterface;
use Hryvinskyi\RichSnippets\Model\Clock\ClockInterface;
use Hryvinskyi\RichSnippets\Model\Library\Question\ShopNameInterface;
use Hryvinskyi\RichSnippets\Model\Store\StoreAddresses;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Exports snippets with the default store view's host as the source and the shop's name in the file name
 *
 * The export time is written in ISO 8601, in UTC.
 */
class SnippetExport implements SnippetExportInterface
{
    private const HOST_PATTERN = '#\A[a-z][a-z0-9+.-]*://(?:[^@/?\#]*@)?([^:/?\#]+)#i';

    /**
     * @param SnippetRepositoryInterface $snippets
     * @param PortableConditions $conditions
     * @param PortableStores $stores
     * @param ExportFileNames $fileNames
     * @param ShopNameInterface $shopName
     * @param StoreAddresses $addresses
     * @param StoreManagerInterface $storeManager
     * @param ClockInterface $clock
     * @param TransferFactoryInterface $results
     */
    public function __construct(
        private readonly SnippetRepositoryInterface $snippets,
        private readonly PortableConditions $conditions,
        private readonly PortableStores $stores,
        private readonly ExportFileNames $fileNames,
        private readonly ShopNameInterface $shopName,
        private readonly StoreAddresses $addresses,
        private readonly StoreManagerInterface $storeManager,
        private readonly ClockInterface $clock,
        private readonly TransferFactoryInterface $results
    ) {
    }

    /**
     * @inheritDoc
     */
    public function one(int $id): SnippetExportFileInterface
    {
        $snippet = $this->snippets->get($id);

        return $this->results->exportFile($this->fileNames->one($snippet->getName()), $this->document([$snippet]));
    }

    /**
     * @inheritDoc
     */
    public function all(): SnippetExportFileInterface
    {
        $storeId = $this->defaultStoreId();
        $shop = $this->shopName->name($storeId);

        return $this->results->exportFile(
            $this->fileNames->all($shop !== '' ? $shop : $this->host($storeId)),
            $this->document($this->snippets->list())
        );
    }

    /**
     * The export document of snippets
     *
     * @param list<SnippetInterface> $snippets
     * @return array<string, mixed>
     */
    private function document(array $snippets): array
    {
        return [
            ExportFormat::HEADER => [
                ExportFormat::VERSION => ExportFormat::CURRENT_VERSION,
                ExportFormat::EXPORTED_AT => gmdate(DATE_ATOM, $this->clock->now()),
                ExportFormat::SOURCE => $this->host($this->defaultStoreId()),
            ],
            ExportFormat::SNIPPETS => array_map(
                fn (SnippetInterface $snippet): array => $this->entry($snippet),
                $snippets
            ),
        ];
    }

    /**
     * One snippet as the export writes it
     *
     * @param SnippetInterface $snippet
     * @return array<string, mixed>
     */
    private function entry(SnippetInterface $snippet): array
    {
        $origin = $snippet->getOrigin();

        return [
            ExportFormat::CODE => $snippet->getCode(),
            ExportFormat::NAME => $snippet->getName(),
            ExportFormat::TYPE => $snippet->getType(),
            ExportFormat::BLOCK => $snippet->isBlock(),
            ExportFormat::ACTIVE => $snippet->isActive(),
            ExportFormat::PAGE_KINDS => $snippet->getPageKinds(),
            ExportFormat::CONDITIONS => $this->conditions->exported($snippet->getConditions()),
            ExportFormat::STORES => $this->stores->codesOf($snippet->getStoreIds()),
            ExportFormat::SORT_ORDER => $snippet->getSortOrder(),
            ExportFormat::ORIGIN => $origin === null ? null : [
                ExportFormat::ORIGIN_CODE => $origin->getCode(),
                ExportFormat::ORIGIN_VERSION => $origin->getVersion(),
            ],
            ExportFormat::UPDATED_AT => $snippet->getUpdatedAt(),
            ExportFormat::TEMPLATE => $snippet->getTemplate(),
        ];
    }

    /**
     * The host of a store view's base URL
     *
     * @param int $storeId
     * @return string
     */
    private function host(int $storeId): string
    {
        return preg_match(self::HOST_PATTERN, $this->addresses->page('', $storeId), $match) === 1 ? $match[1] : '';
    }

    /**
     * The default store view's ID
     *
     * @return int
     */
    private function defaultStoreId(): int
    {
        return (int) $this->storeManager->getDefaultStoreView()?->getId();
    }
}
