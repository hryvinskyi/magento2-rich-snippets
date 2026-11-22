<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Page\Kind;

use Hryvinskyi\RichSnippets\Api\PageKindResolverInterface;
use Hryvinskyi\RichSnippets\Model\Page\Cms\StoreCmsPagesInterface;
use Magento\Framework\App\RequestInterface;

/**
 * The home page: its entity is the CMS page the store view configures as its home page, whatever the request says
 */
class HomeKindResolver implements PageKindResolverInterface
{
    /**
     * @param StoreCmsPagesInterface $cmsPages
     * @param array<array-key,string> $actionNames the full action names the kind owns
     */
    public function __construct(
        private readonly StoreCmsPagesInterface $cmsPages,
        private readonly array $actionNames
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getActionNames(): array
    {
        return array_values($this->actionNames);
    }

    /**
     * @inheritDoc
     */
    public function entityId(RequestInterface $request, int $storeId): ?int
    {
        return $this->cmsPages->homePageId($storeId);
    }
}
