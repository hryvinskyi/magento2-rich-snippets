<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Overview;

use Hryvinskyi\RichSnippets\Api\Data\KindSnippetInterface;
use Hryvinskyi\RichSnippets\Api\Data\PlacementCountInterface;
use Hryvinskyi\RichSnippets\Api\Data\SnippetInterface;

/**
 * A page snippet set for a kind of page, with the pages of the kind that meet its conditions
 */
class KindSnippet implements KindSnippetInterface
{
    /**
     * @param SnippetInterface $snippet
     * @param PlacementCountInterface|null $placementCount
     */
    public function __construct(
        private readonly SnippetInterface $snippet,
        private readonly ?PlacementCountInterface $placementCount
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getSnippet(): SnippetInterface
    {
        return $this->snippet;
    }

    /**
     * @inheritDoc
     */
    public function getPlacementCount(): ?PlacementCountInterface
    {
        return $this->placementCount;
    }
}
