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
 * A kind of page whose entity ID is a request parameter (`id` of a product page, `page_id` of a CMS page)
 *
 * A parameter that is missing or not a positive whole number means the request shows no entity.
 */
class RequestParamKindResolver implements PageKindResolverInterface
{
    /**
     * @param array<array-key,string> $actionNames the full action names the kind owns
     * @param string $param the name of the request parameter holding the entity ID
     */
    public function __construct(
        private readonly array $actionNames,
        private readonly string $param
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
        $value = $request->getParam($this->param);
        if (!is_scalar($value) || preg_match('/\A[1-9][0-9]*\z/', (string) $value) !== 1) {
            return null;
        }

        return (int) $value;
    }
}
