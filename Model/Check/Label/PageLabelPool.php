<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Check\Label;

/**
 * Names the pages of every kind through the labels registered for each kind (di.xml); a kind with none registered
 * is named by the fallback
 */
class PageLabelPool
{
    /**
     * @param PageLabelsInterface $fallback the names of the pages of a kind with no labels of its own
     * @param array<string,PageLabelsInterface> $kinds by kind code
     */
    public function __construct(
        private readonly PageLabelsInterface $fallback,
        private readonly array $kinds = []
    ) {
    }

    /**
     * The labels of a kind of page
     *
     * @param string $kind
     * @return PageLabelsInterface
     */
    public function forKind(string $kind): PageLabelsInterface
    {
        return $this->kinds[$kind] ?? $this->fallback;
    }
}
