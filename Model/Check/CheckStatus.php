<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Check;

use Hryvinskyi\RichSnippets\Api\Data\CheckRunInterface;
use Hryvinskyi\RichSnippets\Api\Data\CheckStatusInterface;

/**
 * Where the catalog check stood when it was read
 */
class CheckStatus implements CheckStatusInterface
{
    /**
     * @param CheckRunInterface|null $current
     * @param CheckRunInterface|null $last
     * @param CheckRunInterface|null $failed
     * @param array<string,int> $pagesByKind
     * @param array<string,int> $invalidPagesByKind
     * @param array<string,int> $gapPagesByKind
     */
    public function __construct(
        private readonly ?CheckRunInterface $current = null,
        private readonly ?CheckRunInterface $last = null,
        private readonly ?CheckRunInterface $failed = null,
        private readonly array $pagesByKind = [],
        private readonly array $invalidPagesByKind = [],
        private readonly array $gapPagesByKind = []
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getCurrent(): ?CheckRunInterface
    {
        return $this->current;
    }

    /**
     * @inheritDoc
     */
    public function getLast(): ?CheckRunInterface
    {
        return $this->last;
    }

    /**
     * @inheritDoc
     */
    public function getFailed(): ?CheckRunInterface
    {
        return $this->failed;
    }

    /**
     * @inheritDoc
     */
    public function getPagesByKind(): array
    {
        return $this->pagesByKind;
    }

    /**
     * @inheritDoc
     */
    public function getInvalidPagesByKind(): array
    {
        return $this->invalidPagesByKind;
    }

    /**
     * @inheritDoc
     */
    public function getGapPagesByKind(): array
    {
        return $this->gapPagesByKind;
    }
}
