<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\ViewModel;

use Hryvinskyi\RichSnippets\Api\PageCacheTagsInterface;
use Hryvinskyi\RichSnippets\Api\PageContextInterface;
use Hryvinskyi\RichSnippets\Api\SnippetOutputInterface;
use Hryvinskyi\RichSnippets\Model\Config\OutputSettings;
use Hryvinskyi\RichSnippets\Model\Storefront\OutputPositionPool;
use Magento\Framework\View\Element\Block\ArgumentInterface;

/**
 * The snippets of the page the request renders: written where the store view's position setting says, and the page
 * cache tags the page carries for them
 */
class StorefrontSnippets implements ArgumentInterface
{
    /**
     * @param PageContextInterface $pages
     * @param SnippetOutputInterface $output
     * @param PageCacheTagsInterface $tags
     * @param OutputSettings $settings
     * @param OutputPositionPool $positions
     */
    public function __construct(
        private readonly PageContextInterface $pages,
        private readonly SnippetOutputInterface $output,
        private readonly PageCacheTagsInterface $tags,
        private readonly OutputSettings $settings,
        private readonly OutputPositionPool $positions
    ) {
    }

    /**
     * Write the page's blocks; the answer is what is printed at the end of the body
     *
     * @return string
     */
    public function write(): string
    {
        $page = $this->pages->current();
        $blocks = $this->output->forPage($page);
        if ($blocks === []) {
            return '';
        }
        $storeId = $page->getStoreId();

        return $this->positions->get($this->settings->position($storeId))
            ->write($blocks, $this->settings->marksBlocks($storeId));
    }

    /**
     * The page cache tags of the page, carried whether or not it writes a snippet
     *
     * @return list<string>
     */
    public function identities(): array
    {
        return $this->tags->forPage($this->pages->current());
    }
}
