<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\PageTest;

use Hryvinskyi\RichSnippets\Api\Data\PageEntityInterface;
use Hryvinskyi\RichSnippets\Api\PageEntityFactoryInterface;
use Hryvinskyi\RichSnippets\Api\PageSearchInterface;
use Hryvinskyi\RichSnippets\Api\StorefrontPathInterface;
use Hryvinskyi\RichSnippets\Model\Page\Cms\StoreCmsPagesInterface;
use Hryvinskyi\RichSnippets\Model\Store\StoreAddresses;
use Hryvinskyi\RichSnippets\Model\Storefront\Reading\AddressParser;
use Magento\UrlRewrite\Model\UrlFinderInterface;
use Magento\UrlRewrite\Service\V1\Data\UrlRewrite;

/**
 * Works out what a page test reads: the path of a page of the shop, or the page a path shows
 *
 * A page of the shop is read at its canonical address on its store view. A path shows the home page when it is
 * empty, the entity whose URL rewrite it is when it has one of a kind of page listed in the container configuration,
 * and a page of the "every other page" kind otherwise.
 *
 * @phpstan-type Tested array{page: PageEntityInterface, label: string, path: string}
 */
class TestedPages
{
    /**
     * @param PageSearchInterface $pageSearch
     * @param StoreAddresses $addresses
     * @param StorefrontPathInterface $paths
     * @param UrlFinderInterface $rewrites
     * @param PageEntityFactoryInterface $pageFactory
     * @param StoreCmsPagesInterface $cmsPages
     * @param AddressParser $parser
     * @param array<string,string> $rewriteKinds the kind of page by URL rewrite entity type
     * @param string $homeKind
     * @param string $otherKind
     */
    public function __construct(
        private readonly PageSearchInterface $pageSearch,
        private readonly StoreAddresses $addresses,
        private readonly StorefrontPathInterface $paths,
        private readonly UrlFinderInterface $rewrites,
        private readonly PageEntityFactoryInterface $pageFactory,
        private readonly StoreCmsPagesInterface $cmsPages,
        private readonly AddressParser $parser,
        private readonly array $rewriteKinds = [],
        private readonly string $homeKind = 'home',
        private readonly string $otherKind = 'other'
    ) {
    }

    /**
     * A page of the shop, with its name and the path it is read at
     *
     * @param PageEntityInterface $page
     * @return Tested
     * @throws \InvalidArgumentException when the page does not exist in its store view
     */
    public function ofPage(PageEntityInterface $page): array
    {
        $match = $this->pageSearch->find($page);
        if ($match === null) {
            throw new \InvalidArgumentException('The page does not exist in its store view.');
        }

        return [
            'page' => $page,
            'label' => $match->getLabel(),
            'path' => $this->addresses->pathOf($match->getUrl(), $page->getStoreId()) ?? '/',
        ];
    }

    /**
     * The page a path of a store view shows, named by its path
     *
     * @param string $path
     * @param int $storeId
     * @return Tested
     * @throws \InvalidArgumentException when the path is not a storefront path
     */
    public function ofPath(string $path, int $storeId): array
    {
        $path = $this->paths->normalize($path);

        return ['page' => $this->pageAt($path, $storeId), 'label' => $path, 'path' => $path];
    }

    /**
     * The page a normalised path shows
     *
     * @param string $path
     * @param int $storeId
     * @return PageEntityInterface
     */
    private function pageAt(string $path, int $storeId): PageEntityInterface
    {
        $requestPath = $this->parser->decode(ltrim(explode('?', $path, 2)[0], '/'));
        if ($requestPath === '') {
            return $this->pageFactory->create($this->homeKind, $this->cmsPages->homePageId($storeId), $storeId);
        }
        $rewrite = $this->rewrites->findOneByData([
            UrlRewrite::REQUEST_PATH => $requestPath,
            UrlRewrite::STORE_ID => $storeId,
        ]);
        $kind = $rewrite === null ? null : ($this->rewriteKinds[(string) $rewrite->getEntityType()] ?? null);
        $entityId = $rewrite === null ? 0 : (int) $rewrite->getEntityId();
        if ($kind === null || $entityId < 1) {
            return $this->pageFactory->create($this->otherKind, null, $storeId);
        }

        return $this->pageFactory->create($kind, $entityId, $storeId);
    }
}
