<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Storefront;

use Hryvinskyi\RichSnippets\Api\Data\PageEntityInterface;
use Hryvinskyi\RichSnippets\Api\Data\RenderedBlockInterface;
use Hryvinskyi\RichSnippets\Model\Config\OutputSettings;
use Magento\Framework\ObjectManager\ResetAfterRequestInterface;

/**
 * Which of the theme's own microdata items the page the current request renders loses
 *
 * The page loses any only when it is of a kind whose theme markup describes the entity a snippet now describes
 * (product pages), it wrote a block whose snippet type describes that entity (Product, ProductGroup) and the store
 * view's setting says to remove the markup. It then loses the theme's items of every type its blocks are written as,
 * and of every type that describes the entity, since a ProductGroup snippet replaces the theme's Product item as a
 * Product snippet does. Every other item stays, and a page that does not qualify keeps all of its markup, so no
 * page is left without a description of what a snippet does not describe. The storefront records the page's blocks
 * once they are written; the decision is then read when the response is finished, without evaluating anything again.
 */
class ThemeMarkupDecision implements ResetAfterRequestInterface
{
    /**
     * @var list<string>
     */
    private array $replacedTypes = [];

    /**
     * @param OutputSettings $settings
     * @param ThemeMarkupTypes $types the snippet types that describe the page's entity, any one of which replaces the
     *     theme's items of all of them
     * @param array<string,string> $pageKinds the kinds of page whose theme markup can be taken out
     */
    public function __construct(
        private readonly OutputSettings $settings,
        private readonly ThemeMarkupTypes $types,
        private readonly array $pageKinds = []
    ) {
    }

    /**
     * Record the blocks the request's page wrote
     *
     * @param PageEntityInterface $page
     * @param list<RenderedBlockInterface> $blocks
     * @return void
     */
    public function record(PageEntityInterface $page, array $blocks): void
    {
        $written = array_map(static fn (RenderedBlockInterface $block): string => $block->getType(), $blocks);
        $types = $this->types->all();
        $removes = in_array($page->getKind(), $this->pageKinds, true)
            && array_intersect($written, $types) !== []
            && $this->settings->removesThemeMarkup($page->getStoreId());
        $this->replacedTypes = $removes ? array_values(array_unique(array_filter(
            [...$types, ...$written],
            static fn (string $type): bool => $type !== ''
        ))) : [];
    }

    /**
     * The schema.org types of the theme's items that go from the request's page; empty when the page keeps them all
     *
     * @return list<string>
     */
    public function replacedTypes(): array
    {
        return $this->replacedTypes;
    }

    /**
     * @inheritDoc
     */
    public function _resetState(): void
    {
        $this->replacedTypes = [];
    }
}
