<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Flag;

use Hryvinskyi\RichSnippets\Api\Data\FlagTone;
use Hryvinskyi\RichSnippets\Api\Data\SnippetFlagInterface;

/**
 * A flag on a snippet's row
 */
class SnippetFlag implements SnippetFlagInterface
{
    /**
     * @param FlagTone $tone
     * @param string $text
     */
    public function __construct(
        private readonly FlagTone $tone,
        private readonly string $text
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getTone(): FlagTone
    {
        return $this->tone;
    }

    /**
     * @inheritDoc
     */
    public function getText(): string
    {
        return $this->text;
    }
}
