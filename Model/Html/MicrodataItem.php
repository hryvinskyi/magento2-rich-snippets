<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Html;

use Hryvinskyi\RichSnippets\Api\Data\MicrodataItemInterface;

/**
 * A schema.org item described with microdata
 */
class MicrodataItem implements MicrodataItemInterface
{
    /**
     * @param string $type
     * @param list<string> $properties
     * @throws \InvalidArgumentException when the type is empty
     */
    public function __construct(
        private readonly string $type,
        private readonly array $properties
    ) {
        if ($type === '') {
            throw new \InvalidArgumentException('A microdata item has a type.');
        }
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
    public function getProperties(): array
    {
        return $this->properties;
    }
}
