<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Page;

use Hryvinskyi\RichSnippets\Api\Data\PageEntityInterface;
use Hryvinskyi\RichSnippets\Api\PageContextInterface;
use Hryvinskyi\RichSnippets\Api\PageEntityFactoryInterface;
use Hryvinskyi\RichSnippets\Api\PageKindPoolInterface;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Store\Model\StoreManagerInterface;

/**
 * The page the storefront request renders: the kind that owns its full action name, the entity that kind reads from
 * the request, the current store view
 *
 * Nothing is remembered between calls, so a second request in the same process is read afresh.
 */
class StorefrontPageContext implements PageContextInterface
{
    /**
     * @param HttpRequest $request
     * @param PageKindPoolInterface $kinds
     * @param StoreManagerInterface $storeManager
     * @param PageEntityFactoryInterface $pages
     */
    public function __construct(
        private readonly HttpRequest $request,
        private readonly PageKindPoolInterface $kinds,
        private readonly StoreManagerInterface $storeManager,
        private readonly PageEntityFactoryInterface $pages
    ) {
    }

    /**
     * @inheritDoc
     */
    public function current(): PageEntityInterface
    {
        $storeId = (int) $this->storeManager->getStore()->getId();
        $kind = $this->kinds->kindOfAction($this->request->getFullActionName());

        return $this->pages->create($kind, $this->kinds->get($kind)->entityId($this->request, $storeId), $storeId);
    }
}
