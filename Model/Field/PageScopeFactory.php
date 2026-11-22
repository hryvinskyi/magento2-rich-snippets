<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Field;

use Hryvinskyi\JsonTemplate\Api\ScopeFactoryInterface;
use Hryvinskyi\JsonTemplate\Api\ScopeInterface;
use Hryvinskyi\RichSnippets\Api\Data\PageEntityInterface;
use Hryvinskyi\RichSnippets\Api\PageScopeFactoryInterface;
use Hryvinskyi\RichSnippets\Model\Field\Value\PageContextValue;
use Hryvinskyi\RichSnippets\Model\Field\Value\PageContextValueFactory;

/**
 * A scope over the snippets' own variable providers that knows the page it evaluates for
 */
class PageScopeFactory implements PageScopeFactoryInterface
{
    /**
     * @param ScopeFactoryInterface $scopes
     * @param PageContextValueFactory $contexts
     */
    public function __construct(
        private readonly ScopeFactoryInterface $scopes,
        private readonly PageContextValueFactory $contexts
    ) {
    }

    /**
     * @inheritDoc
     */
    public function create(PageEntityInterface $page): ScopeInterface
    {
        return $this->scopes->create([PageContextValue::ROOT => $this->contexts->create(['page' => $page])]);
    }
}
