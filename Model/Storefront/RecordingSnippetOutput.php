<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Storefront;

use Hryvinskyi\RichSnippets\Api\SnippetOutputInterface;
use Hryvinskyi\RichSnippets\Api\Data\PageEntityInterface;

/**
 * The storefront's output: the blocks of the request's page, recorded for the decisions taken once the response is
 * finished
 */
class RecordingSnippetOutput implements SnippetOutputInterface
{
    /**
     * @param SnippetOutputInterface $inner
     * @param ThemeMarkupDecision $themeMarkup
     */
    public function __construct(
        private readonly SnippetOutputInterface $inner,
        private readonly ThemeMarkupDecision $themeMarkup
    ) {
    }

    /**
     * @inheritDoc
     */
    public function forPage(PageEntityInterface $page): array
    {
        $blocks = $this->inner->forPage($page);
        $this->themeMarkup->record($page, $blocks);

        return $blocks;
    }
}
