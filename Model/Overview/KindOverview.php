<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Overview;

use Hryvinskyi\RichSnippets\Api\Data\KindOverviewInterface;
use Hryvinskyi\RichSnippets\Api\Data\KindSnippetInterface;

/**
 * One kind of page on the Overview's page map
 */
class KindOverview implements KindOverviewInterface
{
    /**
     * @param string $kind
     * @param int|null $pageCount
     * @param int|null $sampleEntityId
     * @param list<KindSnippetInterface> $snippets
     */
    public function __construct(
        private readonly string $kind,
        private readonly ?int $pageCount,
        private readonly ?int $sampleEntityId,
        private readonly array $snippets
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getKind(): string
    {
        return $this->kind;
    }

    /**
     * @inheritDoc
     */
    public function getPageCount(): ?int
    {
        return $this->pageCount;
    }

    /**
     * @inheritDoc
     */
    public function getSampleEntityId(): ?int
    {
        return $this->sampleEntityId;
    }

    /**
     * @inheritDoc
     */
    public function getSnippets(): array
    {
        return $this->snippets;
    }
}
