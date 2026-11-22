<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\PageTest;

use Hryvinskyi\RichSnippets\Api\Data\SnippetInterface;
use Hryvinskyi\RichSnippets\Api\Data\UnwrittenReason;
use Hryvinskyi\RichSnippets\Api\Data\UnwrittenSnippetInterface;

/**
 * A snippet set for a page that the page as read does not carry
 */
class UnwrittenSnippet implements UnwrittenSnippetInterface
{
    /**
     * @param SnippetInterface $snippet
     * @param UnwrittenReason $reason
     */
    public function __construct(
        private readonly SnippetInterface $snippet,
        private readonly UnwrittenReason $reason
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
    public function getReason(): UnwrittenReason
    {
        return $this->reason;
    }
}
