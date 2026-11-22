<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Page\Kind;

use Hryvinskyi\RichSnippets\Api\PageKindResolverInterface;
use Magento\Framework\App\RequestInterface;

/**
 * A kind of page that shows no entity, such as "every other page"
 */
class NoEntityKindResolver implements PageKindResolverInterface
{
    /**
     * @param array<array-key,string> $actionNames the full action names the kind owns; none for the fallback kind
     */
    public function __construct(private readonly array $actionNames = [])
    {
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
        return null;
    }
}
