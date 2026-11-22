<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\PageTest;

use Hryvinskyi\RichSnippets\Api\Data\MicrodataItemInterface;
use Hryvinskyi\RichSnippets\Api\Data\PageEntityInterface;
use Hryvinskyi\RichSnippets\Api\Data\PageTestResultInterface;
use Hryvinskyi\RichSnippets\Api\Data\ReadFailure;
use Hryvinskyi\RichSnippets\Api\Data\SnippetExclusionInterface;
use Hryvinskyi\RichSnippets\Api\Data\TestedBlockInterface;
use Hryvinskyi\RichSnippets\Api\Data\UnwrittenSnippetInterface;

/**
 * What one page test found
 */
class PageTestResult implements PageTestResultInterface
{
    /**
     * @param PageEntityInterface $page
     * @param string $label
     * @param string $path
     * @param string|null $readPath
     * @param int $testedAt
     * @param int $durationMs
     * @param ReadFailure|null $failure
     * @param int|null $httpStatus
     * @param list<TestedBlockInterface> $blocks
     * @param list<MicrodataItemInterface> $themeMarkup
     * @param list<SnippetExclusionInterface> $exclusions
     * @param list<UnwrittenSnippetInterface> $unwritten
     * @throws \InvalidArgumentException when a failed test carries findings, or a read one carries no read path
     */
    public function __construct(
        private readonly PageEntityInterface $page,
        private readonly string $label,
        private readonly string $path,
        private readonly ?string $readPath,
        private readonly int $testedAt,
        private readonly int $durationMs,
        private readonly ?ReadFailure $failure = null,
        private readonly ?int $httpStatus = null,
        private readonly array $blocks = [],
        private readonly array $themeMarkup = [],
        private readonly array $exclusions = [],
        private readonly array $unwritten = []
    ) {
        if ($failure !== null && ($blocks !== [] || $themeMarkup !== [] || $exclusions !== [] || $unwritten !== [])) {
            throw new \InvalidArgumentException('A page that could not be read has nothing found on it.');
        }
        if (($failure === null) === ($readPath === null)) {
            throw new \InvalidArgumentException('A page has a read path exactly when it was read.');
        }
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
    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * @inheritDoc
     */
    public function getReadPath(): ?string
    {
        return $this->readPath;
    }

    /**
     * @inheritDoc
     */
    public function getTestedAt(): int
    {
        return $this->testedAt;
    }

    /**
     * @inheritDoc
     */
    public function getDurationMs(): int
    {
        return $this->durationMs;
    }

    /**
     * @inheritDoc
     */
    public function getFailure(): ?ReadFailure
    {
        return $this->failure;
    }

    /**
     * @inheritDoc
     */
    public function getHttpStatus(): ?int
    {
        return $this->httpStatus;
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
    public function getThemeMarkup(): array
    {
        return $this->themeMarkup;
    }

    /**
     * @inheritDoc
     */
    public function getExclusions(): array
    {
        return $this->exclusions;
    }

    /**
     * @inheritDoc
     */
    public function getUnwritten(): array
    {
        return $this->unwritten;
    }
}
