<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Requirement\Check;

use Hryvinskyi\RichSnippets\Api\Data\FeatureCheckInterface;
use Hryvinskyi\RichSnippets\Api\Data\LeftOutPropertyInterface;
use Hryvinskyi\RichSnippets\Api\Data\OutputCheckInterface;

/**
 * One block compared with the rules of its types
 */
class OutputCheck implements OutputCheckInterface
{
    /**
     * @param string $type
     * @param list<FeatureCheckInterface> $features
     * @param list<LeftOutPropertyInterface> $leftOut
     */
    public function __construct(
        private readonly string $type,
        private readonly array $features = [],
        private readonly array $leftOut = []
    ) {
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
    public function getFeatures(): array
    {
        return $this->features;
    }

    /**
     * @inheritDoc
     */
    public function hasRule(): bool
    {
        return $this->features !== [];
    }

    /**
     * @inheritDoc
     */
    public function getLeftOut(): array
    {
        return $this->leftOut;
    }
}
