<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Overview;

use Hryvinskyi\RichSnippets\Api\Data\AttentionTargetInterface;

/**
 * The admin screen that fixes a thing that wants fixing
 */
class AttentionTarget implements AttentionTargetInterface
{
    /**
     * @param string $route
     * @param array<string,string> $params
     */
    public function __construct(
        private readonly string $route,
        private readonly array $params
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getRoute(): string
    {
        return $this->route;
    }

    /**
     * @inheritDoc
     */
    public function getParams(): array
    {
        return $this->params;
    }
}
