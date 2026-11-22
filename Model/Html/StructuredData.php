<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Html;

use Hryvinskyi\RichSnippets\Api\Data\JsonLdBlockInterface;
use Hryvinskyi\RichSnippets\Api\Data\MicrodataItemInterface;
use Hryvinskyi\RichSnippets\Api\Data\StructuredDataInterface;

/**
 * The structured data found in a page
 */
class StructuredData implements StructuredDataInterface
{
    /**
     * @param list<JsonLdBlockInterface> $blocks
     * @param list<MicrodataItemInterface> $microdata
     */
    public function __construct(
        private readonly array $blocks,
        private readonly array $microdata
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getBlocks(): array
    {
        return $this->blocks;
    }

    /**
     * @inheritDoc
     */
    public function getMicrodata(): array
    {
        return $this->microdata;
    }
}
