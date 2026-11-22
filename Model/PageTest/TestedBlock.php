<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\PageTest;

use Hryvinskyi\RichSnippets\Api\Data\JsonLdBlockInterface;
use Hryvinskyi\RichSnippets\Api\Data\OutputCheckInterface;
use Hryvinskyi\RichSnippets\Api\Data\SnippetInterface;
use Hryvinskyi\RichSnippets\Api\Data\TestedBlockInterface;

/**
 * A block of a tested page, with its snippet and its checks
 */
class TestedBlock implements TestedBlockInterface
{
    /**
     * @param JsonLdBlockInterface $block
     * @param SnippetInterface|null $snippet
     * @param string $type
     * @param list<array<mixed>> $objects
     * @param list<OutputCheckInterface> $checks
     */
    public function __construct(
        private readonly JsonLdBlockInterface $block,
        private readonly ?SnippetInterface $snippet,
        private readonly string $type,
        private readonly array $objects,
        private readonly array $checks
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getBlock(): JsonLdBlockInterface
    {
        return $this->block;
    }

    /**
     * @inheritDoc
     */
    public function getSnippet(): ?SnippetInterface
    {
        return $this->snippet;
    }

    /**
     * @inheritDoc
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * @inheritDoc
     */
    public function getObjects(): array
    {
        return $this->objects;
    }

    /**
     * @inheritDoc
     */
    public function getChecks(): array
    {
        return $this->checks;
    }
}
