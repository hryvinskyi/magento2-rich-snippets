<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Draft;

use Hryvinskyi\RichSnippets\Api\Data\DraftProblemInterface;
use Hryvinskyi\RichSnippets\Api\Data\DraftSaveResultInterface;
use Hryvinskyi\RichSnippets\Api\Data\SnippetInterface;

/**
 * What saving a draft came to
 */
class DraftSaveResult implements DraftSaveResultInterface
{
    /**
     * @param SnippetInterface|null $snippet
     * @param list<DraftProblemInterface> $problems
     * @throws \InvalidArgumentException unless there is either a snippet or a problem
     */
    public function __construct(
        private readonly ?SnippetInterface $snippet,
        private readonly array $problems = []
    ) {
        if (($snippet === null) === ($problems === [])) {
            throw new \InvalidArgumentException('A save stores the snippet or is stopped by a problem.');
        }
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
    public function getProblems(): array
    {
        return $this->problems;
    }
}
