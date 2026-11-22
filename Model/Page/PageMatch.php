<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Page;

use Hryvinskyi\RichSnippets\Api\Data\PageEntityInterface;
use Hryvinskyi\RichSnippets\Api\Data\PageMatchInterface;

/**
 * A page found by the page search
 */
class PageMatch implements PageMatchInterface
{
    /**
     * @param PageEntityInterface $page
     * @param string $label
     * @param string $url
     */
    public function __construct(
        private readonly PageEntityInterface $page,
        private readonly string $label,
        private readonly string $url
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getPage(): PageEntityInterface
    {
        return $this->page;
    }

    /**
     * @inheritDoc
     */
    public function getLabel(): string
    {
        return $this->label;
    }

    /**
     * @inheritDoc
     */
    public function getUrl(): string
    {
        return $this->url;
    }
}
