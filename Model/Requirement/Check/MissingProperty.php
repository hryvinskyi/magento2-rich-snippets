<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Requirement\Check;

use Hryvinskyi\RichSnippets\Api\Data\MissingPropertyInterface;
use Hryvinskyi\RichSnippets\Api\Data\PropertyTone;

/**
 * A property a block misses, with how much it matters and the variables the template found empty for it
 */
class MissingProperty implements MissingPropertyInterface
{
    /**
     * @param string $ruleCode
     * @param string $path
     * @param PropertyTone $tone
     * @param list<string> $sources
     */
    public function __construct(
        private readonly string $ruleCode,
        private readonly string $path,
        private readonly PropertyTone $tone,
        private readonly array $sources = []
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getRuleCode(): string
    {
        return $this->ruleCode;
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
    public function getTone(): PropertyTone
    {
        return $this->tone;
    }

    /**
     * @inheritDoc
     */
    public function getSources(): array
    {
        return $this->sources;
    }
}
